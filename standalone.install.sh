#!/bin/bash

if [ "$#" -ne 1 ]; then
        echo "Please provide your microservice name!"
else
        mkdir ./"$1"
        mv ./* "$1"/;
        composer create-project -sdev zfcampus/zf-apigility-skeleton app;
        mv "$1" app/module/"$1";
        mv app/* .;
        php module/"$1"/merge.composer.php module/"$1"/composer.json composer.json;
        rm -rf composer.lock;
        composer install --no-ansi --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader;
        mv module/"$1"/config/modules.config.php config/modules.config.php;
        mv module/"$1"/config/user.global.php config/autoload/user.global.php;
        mv module/"$1"/config/constants.config.php config/constants.config.php;
        mv module/"$1"/data/* data;
        mv module/"$1"/tests tests;
        mv public/.htaccess public/dev.htaccess;
        wget http://gitlab.dcs/trip-microservices/how-to-create-a-microservice/raw/master/public/.htaccess -P ./public/

        for file in $( find ./module/MicroIce*/config -name *.dist )
        do
                newfile=$( basename $file .dist )
                mv $file ./config/autoload/$newfile
                echo moved file $file to ./config/autoload/$newfile
        done

fi
