-- Adds the 'pending' placement status (automatic placement waiting for Ministry approval).
ALTER TABLE placements MODIFY status ENUM('pending','confirmed','ongoing','completed') NOT NULL DEFAULT 'confirmed';
