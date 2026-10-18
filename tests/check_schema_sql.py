#!/usr/bin/env python3
"""SQLite SQL smoke test (engine-level only, not PHP pdo_sqlite integration)."""
from pathlib import Path
import sqlite3
import re

root = Path(__file__).resolve().parents[1]
src = (root / 'src/Persistence/SqliteUserRepository.php').read_text()
statements = re.findall(r"->prepare\('([^']+)'\)", src)
assert len(statements) == 6, f'Expected 6 prepared statements, found {len(statements)}'
find_id, find_email, insert_user, verify_user, change_email, claim_slot = statements

db = sqlite3.connect(':memory:')
db.executescript((root / 'scripts/schema.sql').read_text())
db.execute(insert_user, {'email': 'alice@example.com', 'hash': 'hash-of-password'})
id = db.execute(find_email, {'email': 'alice@example.com'}).fetchone()[0]
assert id == 1
assert db.execute(find_id, {'id': 1}).fetchone()[3] == 0
try:
    db.execute(insert_user, {'email': 'ALICE@EXAMPLE.COM', 'hash': 'hash'})
    raise AssertionError('UNIQUE COLLATE NOCASE should reject duplicate emails')
except sqlite3.IntegrityError:
    pass

now = 1790000000
assert db.execute(claim_slot, {'id': 1, 'now': now, 'earliest': now - 60}).rowcount == 1
assert db.execute(claim_slot, {'id': 1, 'now': now + 59, 'earliest': now - 1}).rowcount == 0
assert db.execute(claim_slot, {'id': 1, 'now': now + 60, 'earliest': now}).rowcount == 1
assert db.execute(change_email, {'id': 1, 'email': 'new@example.com'}).rowcount == 1
assert db.execute(find_id, {'id': 1}).fetchone()[1] == 'new@example.com'
assert db.execute(find_id, {'id': 1}).fetchone()[4] == now + 60

db.commit()
db.execute('BEGIN IMMEDIATE TRANSACTION')
assert db.execute(verify_user, {'id': 1}).rowcount == 1
db.execute('COMMIT')
assert db.execute(find_id, {'id': 1}).fetchone()[3] == 1
print('PASS: schema and all 6 extracted SQLite statements: insert, read, duplicate, email change, cooldown, transaction, verification.')
