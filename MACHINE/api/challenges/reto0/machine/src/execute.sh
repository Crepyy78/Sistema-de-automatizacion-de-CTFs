#! /bin/bash

while [ 1 == 1 ]; do

	cd /home/user1/bin/

	socat TCP-LISTEN:54475,fork,reuseaddr EXEC:"sudo -u user1 /home/user1/bin/programa"

	sleep 60;

done
