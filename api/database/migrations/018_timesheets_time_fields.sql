ALTER TABLE timesheets ADD COLUMN from_time TIME NULL AFTER work_date;
ALTER TABLE timesheets ADD COLUMN to_time TIME NULL AFTER from_time;
