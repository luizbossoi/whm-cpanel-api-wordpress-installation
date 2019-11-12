FROM nimmis/apache-php7
#WORKDIR /
RUN a2enmod rewrite
RUN apt update
RUN apt install --no-install-recommends --no-upgrade php7.0-mbstring -y
RUN apt install --no-install-recommends --no-upgrade php7.0-ftp -y
RUN apt install --no-install-recommends --no-upgrade php7.0-zip -y

EXPOSE 80

