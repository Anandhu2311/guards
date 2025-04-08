CREATE TABLE IF NOT EXISTS medical_notes (
  id int(11) NOT NULL AUTO_INCREMENT,
  booking_id int(11) NOT NULL,
  symptoms text DEFAULT NULL,
  diagnosis text DEFAULT NULL,
  medication text DEFAULT NULL,
  further_steps text DEFAULT NULL,
  status varchar(20) DEFAULT 'pending',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  KEY booking_id (booking_id),
  CONSTRAINT medical_notes_ibfk_1 FOREIGN KEY (booking_id) REFERENCES bookings (booking_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 