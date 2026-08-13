INSERT INTO booking_statuses (status_key, name, description, sort_order, is_blocking, is_active, created_at, updated_at)
SELECT 'request', 'Request', 'Booking request received and waiting for manual review.', 10, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM booking_statuses WHERE status_key = 'request'
);

INSERT INTO booking_statuses (status_key, name, description, sort_order, is_blocking, is_active, created_at, updated_at)
SELECT 'approved', 'Approved', 'Booking has been manually approved and reserves the item.', 20, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM booking_statuses WHERE status_key = 'approved'
);

INSERT INTO booking_statuses (status_key, name, description, sort_order, is_blocking, is_active, created_at, updated_at)
SELECT 'rejected', 'Rejected', 'Booking request has been rejected and does not block the calendar.', 30, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM booking_statuses WHERE status_key = 'rejected'
);

INSERT INTO booking_statuses (status_key, name, description, sort_order, is_blocking, is_active, created_at, updated_at)
SELECT 'cancelled', 'Cancelled', 'Booking has been cancelled and does not block the calendar.', 40, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM booking_statuses WHERE status_key = 'cancelled'
);

INSERT INTO booking_statuses (status_key, name, description, sort_order, is_blocking, is_active, created_at, updated_at)
SELECT 'active', 'Active', 'Item is handed out or the rental is ongoing and blocks the calendar.', 50, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM booking_statuses WHERE status_key = 'active'
);

INSERT INTO booking_statuses (status_key, name, description, sort_order, is_blocking, is_active, created_at, updated_at)
SELECT 'completed', 'Completed', 'Booking is completed and no longer blocks future calendar dates.', 60, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM booking_statuses WHERE status_key = 'completed'
);
