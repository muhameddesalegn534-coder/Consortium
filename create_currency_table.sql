-- Currency rates table for Consortium Hub
CREATE TABLE `currency_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cluster_id` int(11) NOT NULL,
  `from_currency` varchar(3) NOT NULL,
  `to_currency` varchar(3) NOT NULL,
  `exchange_rate` decimal(10,4) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cluster_currency_pair` (`cluster_id`, `from_currency`, `to_currency`),
  KEY `idx_cluster_active` (`cluster_id`, `is_active`),
  KEY `idx_updated_by` (`updated_by`),
  FOREIGN KEY (`cluster_id`) REFERENCES `clusters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default rates for existing clusters
INSERT INTO `currency_rates` (`cluster_id`, `from_currency`, `to_currency`, `exchange_rate`) VALUES
(1, 'USD', 'ETB', 300.0000),
(1, 'EUR', 'ETB', 320.0000),
(1, 'USD', 'EUR', 0.9375),
(2, 'USD', 'ETB', 300.0000),
(2, 'EUR', 'ETB', 320.0000),
(2, 'USD', 'EUR', 0.9375);