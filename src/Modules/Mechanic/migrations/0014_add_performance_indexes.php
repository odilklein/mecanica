<?php
// =============================================
// MIGRATION — índices para consultas frequentes
// (listagens, filtros de status/data, sino e buscas).
// =============================================

return [
    'up' => [
        "ALTER TABLE service_orders
            ADD INDEX idx_so_status_entry (status, entry_date),
            ADD INDEX idx_so_entry (entry_date),
            ADD INDEX idx_so_completion (completion_date)",

        "ALTER TABLE part_orders
            ADD INDEX idx_po_status (status),
            ADD INDEX idx_po_expected (expected_date)",

        "ALTER TABLE budgets
            ADD INDEX idx_budgets_status_date (status, budget_date)",

        "ALTER TABLE tickets
            ADD INDEX idx_tickets_status_updated (status, updated_at)",

        "ALTER TABLE notifications
            ADD INDEX idx_notifications_user_read (user_id, read_at)",

        "ALTER TABLE service_order_tasks
            ADD INDEX idx_sot_order_status (order_id, status)",

        "ALTER TABLE clients
            ADD INDEX idx_clients_name (name),
            ADD INDEX idx_clients_email (email),
            ADD INDEX idx_clients_phone (phone)",

        "ALTER TABLE vehicles
            ADD INDEX idx_vehicles_active_plate (is_active, plate)",

        "ALTER TABLE users
            ADD INDEX idx_users_role_active (role, is_active)",

        "ALTER TABLE audit_logs
            ADD INDEX idx_audit_entity (entity, entity_id),
            ADD INDEX idx_audit_created (created_at)",
    ],
    'down' => [
        "ALTER TABLE service_orders
            DROP INDEX idx_so_status_entry,
            DROP INDEX idx_so_entry,
            DROP INDEX idx_so_completion",
        "ALTER TABLE part_orders DROP INDEX idx_po_status, DROP INDEX idx_po_expected",
        "ALTER TABLE budgets DROP INDEX idx_budgets_status_date",
        "ALTER TABLE tickets DROP INDEX idx_tickets_status_updated",
        "ALTER TABLE notifications DROP INDEX idx_notifications_user_read",
        "ALTER TABLE service_order_tasks DROP INDEX idx_sot_order_status",
        "ALTER TABLE clients
            DROP INDEX idx_clients_name,
            DROP INDEX idx_clients_email,
            DROP INDEX idx_clients_phone",
        "ALTER TABLE vehicles DROP INDEX idx_vehicles_active_plate",
        "ALTER TABLE users DROP INDEX idx_users_role_active",
        "ALTER TABLE audit_logs DROP INDEX idx_audit_entity, DROP INDEX idx_audit_created",
    ],
];
