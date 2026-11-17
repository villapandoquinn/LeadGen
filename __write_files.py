#!/usr/bin/env python3
"""Write all LinkedIn integration PHP files for ProspectHunt."""
import os

BASE = r'c:\laragon\www\LeadGen'

def wf(relpath, content):
    full = os.path.join(BASE, relpath)
    os.makedirs(os.path.dirname(full), exist_ok=True)
    with open(full, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f'Written: {relpath}')

