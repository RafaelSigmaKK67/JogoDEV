# ============================================================
# DEV SURVIVOR - Dockerfile para deploy em nuvem
# (Railway, Render, Fly.io ou qualquer host de containers)
#
# Local (XAMPP) continua funcionando normalmente sem Docker.
#
# Uso com Railway:
#   1. Crie um projeto e adicione um servico MySQL
#   2. Adicione este repositorio como servico (deploy from GitHub)
#   3. Configure as variaveis: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
#      (use as credenciais do servico MySQL do Railway)
#   4. Importe o install.sql no MySQL do Railway (via cliente mysql ou painel)
# ============================================================
FROM php:8.2-apache

# Extensao PDO MySQL (unica dependencia do projeto)
RUN docker-php-ext-install pdo_mysql

# Habilita rewrite (nao obrigatorio, mas util)
RUN a2enmod rewrite

# Copia o projeto para a raiz do Apache
COPY . /var/www/html/

# Apache escuta na porta definida pelo host (Railway injeta PORT)
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
ENV PORT=8080
EXPOSE 8080

CMD ["apache2-foreground"]
