-- Rerunnable additive migration. It never deletes or replaces application data.
DROP PROCEDURE IF EXISTS add_phase4_growing_column;

DELIMITER //
CREATE PROCEDURE add_phase4_growing_column(
    IN column_name_value VARCHAR(64),
    IN column_definition_value VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'seeds'
          AND COLUMN_NAME = column_name_value
    ) THEN
        SET @phase4_sql = CONCAT(
            'ALTER TABLE seeds ADD COLUMN `',
            REPLACE(column_name_value, '`', '``'),
            '` ',
            column_definition_value
        );
        PREPARE phase4_statement FROM @phase4_sql;
        EXECUTE phase4_statement;
        DEALLOCATE PREPARE phase4_statement;
    END IF;
END//
DELIMITER ;

CALL add_phase4_growing_column('days_to_maturity_min', 'SMALLINT UNSIGNED NULL AFTER `days_to_maturity`');
CALL add_phase4_growing_column('days_to_maturity_max', 'SMALLINT UNSIGNED NULL AFTER `days_to_maturity_min`');
CALL add_phase4_growing_column('maturity_qualifier', 'VARCHAR(120) NULL AFTER `days_to_maturity_max`');
CALL add_phase4_growing_column('indoor_start_status', 'ENUM(''Not Recommended'',''Not Applicable'') NULL AFTER `plantable_months`');
CALL add_phase4_growing_column('direct_sow_status', 'ENUM(''Not Recommended'',''Not Applicable'') NULL AFTER `indoor_end_day`');
CALL add_phase4_growing_column('transplant_status', 'ENUM(''Not Recommended'',''Not Applicable'') NULL AFTER `direct_sow_end_day`');

DROP PROCEDURE add_phase4_growing_column;

-- Backfill only missing new values; preserve the legacy compatibility value.
UPDATE seeds
SET days_to_maturity_min = days_to_maturity,
    days_to_maturity_max = days_to_maturity
WHERE days_to_maturity IS NOT NULL
  AND days_to_maturity_min IS NULL
  AND days_to_maturity_max IS NULL;
