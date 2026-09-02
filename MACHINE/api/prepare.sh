apt update
apt install -y apache2 libapache2-mod-php php-pdo php-mysql curl mariadb-server ca-certificates
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

# Add the repository to Apt sources:
tee /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/debian
Suites: $(. /etc/os-release && echo "$VERSION_CODENAME")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF
apt update

apt install -y docker-ce-cli docker-buildx-plugin docker-compose-plugin

apt clean

a2dismod -f autoindex


if [ ! -d "/var/lib/mysql/mysql" ]; then
  mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
fi
