#!/bin/bash
nohup /opt/venv/bin/sqlite_web -H 127.0.0.1 -p 8081 /data/database.sqlite -u /sql -P ${SQLITE_WEB_PASSWORD} &
apache2-foreground