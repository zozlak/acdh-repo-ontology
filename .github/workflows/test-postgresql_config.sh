#!/bin/bash
echo "listen_addresses = '*'" >> /home/www-data/postgresql/postgresql.conf
echo "" >> /home/www-data/postgresql/pg_hba.conf
