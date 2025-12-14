ALTER TABLE elections ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER status;
ALTER TABLE elections ADD COLUMN approved_by VARCHAR(20) NULL AFTER approval_status;
ALTER TABLE elections ADD COLUMN rejection_reason TEXT NULL AFTER approved_by;
ALTER TABLE elections ADD CONSTRAINT fk_approved_by FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL;
UPDATE elections SET approval_status = 'approved', approved_by = created_by WHERE created_by = 'admin001';
CREATE INDEX idx_approval_status ON elections(approval_status);