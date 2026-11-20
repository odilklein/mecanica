<?php
declare(strict_types=1);

namespace Modules\Core\Controllers;

use Kernel\Auth\Auth;
use Kernel\Http\Controller;
use Kernel\Http\Session;
use Kernel\Menu\Menu;
use Kernel\View\ViewFactory;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Transcrição de áudio (Groq Whisper) usada pelo botão de microfone
 * dos tickets. Só funciona quando GROQ_API_KEY está configurada.
 */
class TranscriptionController extends Controller
{
    private const MAX_BYTES = 20 * 1024 * 1024;
    private const ENDPOINT = 'https://api.groq.com/openai/v1/audio/transcriptions';

    public function __construct(Auth $auth, Session $session, ViewFactory $view, Menu $menu)
    {
        parent::__construct($auth, $session, $view, $menu);
    }

    public function transcribe(ServerRequestInterface $request)
    {
        if (!$this->auth->check()) {
            return json_response(['error' => 'unauthorized'], 401);
        }

        $body = $request->getParsedBody();
        if (!$this->session->verifyCsrf($body['csrf_token'] ?? null)) {
            return json_response(['error' => 'csrf'], 403);
        }

        $apiKey = trim((string) env('GROQ_API_KEY', ''));
        if ($apiKey === '') {
            return json_response(['error' => 'disabled'], 503);
        }

        $upload = $request->getUploadedFiles()['audio'] ?? null;
        if (!$upload || $upload->getError() !== UPLOAD_ERR_OK) {
            return json_response(['error' => 'audio_required'], 422);
        }

        $size = $upload->getSize();
        if ($size !== null && $size > self::MAX_BYTES) {
            return json_response(['error' => 'too_large'], 413);
        }

        // Copia o stream do upload para um arquivo temporário (CURLFile).
        $tmp = tempnam(sys_get_temp_dir(), 'groq_');
        if ($tmp === false) {
            return json_response(['error' => 'server_error'], 500);
        }

        try {
            $stream = $upload->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $out = fopen($tmp, 'wb');
            if ($out === false) {
                return json_response(['error' => 'server_error'], 500);
            }
            while (!$stream->eof()) {
                fwrite($out, $stream->read(8192));
            }
            fclose($out);

            $clientName = (string) ($upload->getClientFilename() ?: 'audio.webm');
            $model = trim((string) env('GROQ_TRANSCRIPTION_MODEL', 'whisper-large-v3-turbo')) ?: 'whisper-large-v3-turbo';

            // Tipo real do arquivo (não confia no MIME declarado pelo cliente).
            $allowed = [
                'audio/webm' => 'webm',
                'video/webm' => 'webm',
                'audio/ogg' => 'ogg',
                'audio/mpeg' => 'mp3',
                'audio/mp3' => 'mp3',
                'audio/mp4' => 'm4a',
                'audio/x-m4a' => 'm4a',
                'audio/wav' => 'wav',
                'audio/x-wav' => 'wav',
                'video/mp4' => 'mp4',
            ];
            $detected = strtolower(trim((string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp)));
            if (!isset($allowed[$detected])) {
                error_log('Transcrição recusada: tipo não suportado (' . $detected . ') em ' . $clientName);
                return json_response(['error' => 'unsupported_type'], 422);
            }
            $mime = $detected;
            $ext = $allowed[$detected];

            $ch = curl_init(self::ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 90,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS => [
                    'file' => new \CURLFile($tmp, $mime, 'audio.' . $ext),
                    'model' => $model,
                    'language' => 'pt',
                    'temperature' => '0',
                    'response_format' => 'json',
                ],
            ]);

            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $status < 200 || $status >= 300) {
                error_log('Groq transcription failed: HTTP ' . $status . ' ' . $curlError . ' ' . mb_substr((string) $raw, 0, 300));
                return json_response(['error' => 'transcription_failed'], 502);
            }

            $data = json_decode((string) $raw, true);
            $text = trim((string) ($data['text'] ?? ''));
            if ($text === '') {
                return json_response(['error' => 'empty_transcript'], 422);
            }

            return json_response(['text' => $text]);
        } finally {
            @unlink($tmp);
        }
    }
}
