Use scripts/database_preflight.php first.
01_V2_SAFE_EXISTING.sql and 02_ADMIN_V3_SAFE_EXISTING.sql are additive migrations for the EXISTING scholarmatch database.
00_FRESH_SCHEMA_ONLY.sql is base schema for fresh databases ONLY.
Do not attempt to fix InnoDB #1813/#1932 by repeated SQL import.
