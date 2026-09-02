#! /bin/bash
apt update && apt -y upgrade && apt -y install sudo passwd socat

ALEAPASS="$RANDOM$RANDOM$RANDOM$RANDOM";

cd /root

INITID=1000;

I=0;

USERNAME="user1";
PASSWORD=$ALEAPASS;

echo "$USERNAME:$PASSWORD:$INITID:$INITID::/home/$USERNAME:/bin/bash" > /root/users.txt

USERS[$I]=$USERNAME;

newusers /root/users.txt

chmod -R 700 /opt/src

cp -r /opt/src/bin/ /home/user1/
chown -R user1:user1 /home/user1/bin/
