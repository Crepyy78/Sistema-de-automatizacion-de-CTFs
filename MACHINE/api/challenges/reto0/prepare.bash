#!/bin/bash

mydir=$( cd ${0%/*} ; pwd )
cd $mydir

defport=0
definstance=${mydir##*/}
INSTANCENAME=${1:-$definstance}
INITPORT=${2:-$defport}
PUBLICINIT=${3:-C0nclave}
PRIVATEINIT=${4:-$RANDOM}
HOST_PWD=${HOST_PWD}

echo "instancename: $INSTANCENAME"
echo "port: $INITPORT"
echo "publicinit: $PUBLICINIT"
echo "privateinit: $PRIVATEINIT"
echo "pwd: $HOST_PWD"

RANDOM=$PRIVATEINIT

# flag generation ... deberia estar en un sitio comun
R=$RANDOM
dlen=32
d=$( printf "GENERA-%s-%d\n" $PUBLICINIT $R | openssl md5 | cut -d ' ' -f 2,2 | head -c $dlen )
flag=$( printf "%s{%s}" "$PUBLICINIT" "$d" )
###

mkdir -p flags
mkdir -p envs
echo $flag > "flags/_${INSTANCENAME}_flag.txt"
chmod 644 "flags/_${INSTANCENAME}_flag.txt"
printf "PORT=%s\n" "$INITPORT" > "envs/.${INSTANCENAME}_env"
printf "INSTANCENAME=%s\n" "$INSTANCENAME" >> "envs/.${INSTANCENAME}_env"
printf "FLAGFILE=%s\n" "${HOST_PWD}/MACHINE/api/challenges/${definstance}/flags/_${INSTANCENAME}_flag.txt" >> "envs/.${INSTANCENAME}_env"
