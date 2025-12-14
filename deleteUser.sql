ALTER TABLE audit_log DROP FOREIGN KEY audit_log_ibfk_1;
ALTER TABLE audit_log MODIFY user_id VARCHAR(20) NOT NULL;
SELECT 'Audit log foreign key constraint removed successfully!' as status;