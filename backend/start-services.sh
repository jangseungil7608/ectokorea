#!/bin/bash

# Supervisor 시작
service supervisor start

# Queue Worker 시작
supervisorctl reread
supervisorctl update
supervisorctl start ectokorea-worker:*

# Apache 시작 (포그라운드)
apache2-foreground