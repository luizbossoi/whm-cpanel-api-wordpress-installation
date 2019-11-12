FROM nimmis/apache-php7
#WORKDIR /
RUN a2enmod rewrite
RUN apt update
RUN apt install --no-install-recommends --no-upgrade php7.0-mbstring -y
RUN apt install --no-install-recommends --no-upgrade php7.0-ftp -y
#RUN chmod 754 /var/www/html/sessions/
#RUN sed -i "s|AllowOverride None|AllowOverride All|g" /etc/apache2/apache2.conf
#CMD ["/usr/sbin/apache2", "-D",  "FOREGROUND"]

EXPOSE 80

