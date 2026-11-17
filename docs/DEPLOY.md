# Guia de publicação do AlpDesk

Este guia usa **Ubuntu 24.04 + Nginx + PHP 8.3 + MariaDB**. Se o servidor ficar dentro da empresa, leia também a seção 9.
Troque `helpdesk.suaempresa.com.br` pelo seu endereço e `seu@email.com.br` pelo e-mail do administrador.

> Antes de qualquer coisa: o repositório do GitHub precisa ser **privado** e **não pode conter** o `.env`, o banco ou as imagens dos chamados (seção 1).

## 1. Tirar as imagens dos chamados do Git

As imagens antigas estão em `public/img/tasks` e foram versionadas. No seu computador:

```
git rm -r --cached public/img/tasks
git add .gitignore public/img/tasks/.htaccess
git commit -m "Remove uploads do repositório"
```

Se o repositório tem **um único commit**, use `git commit --amend` em vez do commit acima e depois `git push --force`.
Se tem mais commits, as imagens continuam no histórico: apague o repositório e crie outro, ou limpe o histórico.
Se o repositório já foi público em algum momento, considere que as imagens foram expostas.

## 2. Preparar o servidor

```
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mariadb-server unzip git composer \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
```

Edite `/etc/php/8.3/fpm/php.ini` e confira estas linhas (depois: `sudo systemctl restart php8.3-fpm`):

```
expose_php = Off
display_errors = Off
upload_max_filesize = 6M
post_max_size = 8M
```

Firewall e acesso:

```
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

No `/etc/ssh/sshd_config`, use `PasswordAuthentication no` (entrada só por chave) e `PermitRootLogin no`.

## 3. Banco de dados

```
sudo mysql_secure_installation
sudo mysql
```

Dentro do MySQL (troque a senha por uma forte):

```
CREATE DATABASE alpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'alpdesk'@'localhost' IDENTIFIED BY 'SENHA-FORTE-AQUI';
GRANT ALL PRIVILEGES ON alpdesk.* TO 'alpdesk'@'localhost';
FLUSH PRIVILEGES;
```

O banco deve aceitar conexões só da própria máquina (`bind-address = 127.0.0.1`, que é o padrão).

## 4. Código e configuração

```
sudo mkdir -p /var/www/alpdesk && sudo chown $USER:www-data /var/www/alpdesk
git clone SEU-REPOSITORIO-PRIVADO /var/www/alpdesk
cd /var/www/alpdesk
composer install --no-dev --optimize-autoloader
cp .env.production.example .env
nano .env                      # preencha APP_URL, banco e e-mail
php artisan key:generate
chmod 640 .env && sudo chown $USER:www-data .env
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

O front (`public/build`) não vai para o Git. No seu computador rode `npm ci && npm run build` e envie a pasta:

```
scp -r public/build usuario@servidor:/var/www/alpdesk/public/
```

## 5. Banco, imagens e dados iniciais

Se você já tem dados no Laragon, exporte o banco (`mysqldump`) e importe no servidor, e copie a pasta `public/img/tasks`
para `/var/www/alpdesk/public/img/tasks` antes do passo das imagens.

```
php artisan migrate --force
php artisan alpdesk:migrar-imagens --simular     # confira o que será movido
php artisan alpdesk:migrar-imagens               # move para storage/app/private/task-images
php artisan alpdesk:importar-equipamentos --simular
php artisan alpdesk:importar-equipamentos
php artisan alpdesk:admin seu@email.com.br        # a conta precisa existir (crie com tinker, seeder ou importando o banco)
php artisan optimize
```

Depois da migração, apague o que sobrou em `public/img/tasks` (menos o `.htaccess`).
Se o comando avisar de arquivos em **quarentena**, eles não eram imagens: não os abra nem os devolva à pasta pública.

## 6. Nginx e HTTPS

```
sudo cp docs/nginx.conf.example /etc/nginx/sites-available/alpdesk
sudo nano /etc/nginx/sites-available/alpdesk     # ajuste o domínio e o caminho
sudo ln -s /etc/nginx/sites-available/alpdesk /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d helpdesk.suaempresa.com.br
```

O DNS do domínio precisa apontar para o servidor antes do `certbot`.

## 7. Backups

```
sudo mkdir -p /var/backups/alpdesk && sudo chown $USER /var/backups/alpdesk
crontab -e
# 0 2 * * * /var/www/alpdesk/scripts/backup.sh
```

O backup guarda 14 dias de banco e imagens. **Copie a pasta para fora do servidor** (outro computador, rclone, rsync).
Teste a restauração de vez em quando: `gunzip < db_AAAA-MM-DD_HHMM.sql.gz | mysql -u alpdesk -p alpdesk` (em um banco de teste).

## 8. Atualizações

```
cd /var/www/alpdesk && ./scripts/deploy.sh
```

## 9. Servidor dentro da empresa (Cloudflare Tunnel + Access)

Boa opção quando os dados dos chamados não devem ir para uma nuvem de terceiros e as lojas precisam acessar de fora:

1. Coloque o domínio no Cloudflare (conta gratuita).
2. No servidor, instale o `cloudflared` e crie um túnel apontando para `http://localhost:80`.
3. Deixe o Nginx escutando só em `127.0.0.1` (`listen 127.0.0.1:80;`). Nada fica exposto no roteador.
4. No painel Zero Trust, crie uma aplicação **Access** para o endereço do túnel e permita só os e-mails da empresa.
   O plano gratuito cobre até 50 usuários.
5. No `.env`: `APP_URL=https://...` e `FORCE_HTTPS=true`.

## 10. Conferência final

```
curl -sI https://helpdesk.suaempresa.com.br | grep -i -E "strict-transport|x-frame|x-content|x-powered|server"
for caminho in /.env /storage/logs/laravel.log /register /; do
  printf "%s -> " "$caminho"; curl -s -o /dev/null -w "%{http_code}\n" "https://helpdesk.suaempresa.com.br$caminho"
done
php artisan config:show app.debug       # deve mostrar false
php artisan test --filter SegurancaTest  # no computador de desenvolvimento
```

Esperado: `/.env` e o log com 403 ou 404, `/register` com 404, `/` com redirecionamento (302) para o login e nenhum `X-Powered-By`.

## 11. O que este pacote ainda não cobre

- **CSP** (política de conteúdo): o site usa scripts de CDN, então exige um ajuste cuidadoso.
- **2FA obrigatório** para administradores (o Jetstream já oferece; cada pessoa ativa em Perfil).
- **Auditoria**: registro de quem alterou o quê.
- **Monitoramento** e alertas (disco, erros, site fora do ar) e **antivírus** para anexos.
- Um **teste de invasão** por alguém de segurança, se o sistema ficar exposto à internet.
- Acompanhe o `storage/logs` na primeira semana e mantenha o sistema e o PHP atualizados.
