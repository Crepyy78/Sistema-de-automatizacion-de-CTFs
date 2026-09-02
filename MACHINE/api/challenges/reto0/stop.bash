#!/bin/bash

mydir=$( cd ${0%/*} ; pwd )
cd $mydir
INSTANCENAME=${1}

docker compose --env-file "envs/.${INSTANCENAME}_env" -p $INSTANCENAME stop
