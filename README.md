# Deploying PHP Applications using php_mod and FPM/FastCGI

## Ways to deploy a PHP  Application

### 1. Installing PHP as an Apache Module

**Steps:**

- Install PHP as an Apache module.
- Apache automatically handles PHP files by passing them to the embedded PHP interpreter.

**Advantages:**

- Simplified setup: No need for explicit configuration to make Apache aware of PHP; it's seamlessly integrated.
- Potentially better performance as the PHP interpreter is embedded within the Apache process.

**Disadvantages**

- *Less flexibility:* Updates and changes to PHP or Apache might require more coordination.
- If there are issues with PHP, it might impact the entire Apache server.


### 2. Installing Apache and PHP Separately

**Steps**

- Install Apache and PHP as separate components.
- Configure Apache to recognize PHP files and pass them to the PHP interpreter.

**Advantages**

- *Flexibility:* You can manage Apache and PHP independently, allowing for easier updates and configuration changes.
- *Modular:* Changes to one component (e.g., Apache) do not necessarily affect the other (e.g., PHP).

**Disadvantages**

- More manual configuration might be required to set up the connection between Apache and PHP.
- May involve additional steps to ensure proper communication between Apache and PHP.



## Installing PHP as an Apache Module(php_mod)

> I am using Centos 7
```
yum update -y
yum install httpd -y
yum install php -y
systemctl restart httpd

httpd -M | grep -i php
# Output: php5_module (shared)

echo "<?php phpinfo(); ?>" > /var/www/html/info.php
```

**Visit** `http://<my-ip>/info.php`

![php_mod info](/images/php_mod.png "php_mod info")


The default config file of apache for php is at: `/etc/httpd/conf.d/php.conf`

```
#
# Cause the PHP interpreter to handle files with a .php extension.
#
<FilesMatch \.php$>
    SetHandler application/x-httpd-php
</FilesMatch>

#
# Allow php to handle Multiviews
#
AddType text/html .php

#
# Add index.php to the list of files that will be served as directory
# indexes.
#
DirectoryIndex index.php

#
# Uncomment the following lines to allow PHP to pretty-print .phps
# files as PHP source code:
#
#<FilesMatch \.phps$>
#    SetHandler application/x-httpd-php-source
#</FilesMatch>

#
# Apache specific PHP configuration options
# those can be override in each configured vhost
#
php_value session.save_handler "files"
php_value session.save_path    "/var/lib/php/session"
```

## Installing Apache and PHP Separately(FPM/FastCGI)

```
yum remove php -y
yum install php-fpm -y
systemctl start php-fpm
systemctl enable php-fpm
```

Create or Edit the configuration file at `/etc/httpd/conf.d/php.conf`

```
#
# Redirect the PHP scripts execution to the FPM backend
#
<FilesMatch \.php$>
    SetHandler "proxy:fcgi://127.0.0.1:9000"
</FilesMatch>

#
# Allow php to handle Multiviews
#
AddType text/html .php

#
# Add index.php to the list of files that will be served as directory
# indexes.
#
DirectoryIndex index.php

#
# The following lines prevent .user.ini files from being viewed by Web clients
#
<Files ".user.ini">
    Require all denied
</Files
```

```
systemctl restart httpd
```

**Visit** `http://<my-ip>/info.php`

![php_fastcgi info](/images/php_fastcgi.png "php_fastcgi info")


- The main FPM configuration file is `/etc/php-fpm.conf`
- FPM can run various pools, each one running PHP scripts with possible different options, the default pool (www) configuration file is `/etc/php-fpm.d/www.conf`

## Separate frontend(apache) and backend(php-fpm) servers

Apache and php-fpm can be configured on different servers.

Say, frontend at `10.0.0.1` and backend at `10.0.0.2`

Configuration at `/etc/php-fpm.d/www.conf`

```
listen = 10.0.0.2:9000
listen.allowed_clients = 10.0.0.1
```

Configuration at `/etc/httpd/conf.d/php.conf`

```
SetHandler "proxy:fcgi://10.0.0.2:9000"
```

```
systemctl restart php-fpm
systemctl restart httpd
```


## Multiple php-fpm backends

Apache server can have multiple php-fpm backend and load balance among them.

Say, 3 php-fpm backens available at `10.0.0.2:9000`, `10.0.0.2:9000` & `10.0.0.2:9000`


Configuration at `/etc/httpd/conf.d/php.conf`

```
#
# Load balancer creation
#
<Proxy balancer://phpfpmlb>
    BalancerMember fcgi://10.0.0.2:9000
    BalancerMember fcgi://10.0.0.3:9000
    BalancerMember fcgi://10.0.0.4:9000
</Proxy>

#
# Redirect PHP execution to the balancer
#
<FilesMatch \.php$>
    SetHandler "proxy:balancer://phpfpmlb"
</FilesMatch>

#
# Allow php to handle Multiviews
#
AddType text/html .php

#
# Add index.php to the list of files that will be served as directory
# indexes.
#
DirectoryIndex index.php

#
# The following lines prevent .user.ini files from being viewed by Web clients.
#
<Files ".user.ini">
    Require all denied
</Files>
```


```
systemctl restart httpd
```

## Unix Domain Socket

By default, FPM listens for incoming requests on a network socket but can use a Unix Domain Socket, which can slightly improve performance.


Configuration at `/etc/php-fpm.d/www.conf`

```
listen = /run/php-fpm/www.sock
listen.owner = apache
listen.mode = 0660
```

Configuration at `/etc/httpd/conf.d/php.conf`

```
#
# Redirect the PHP scripts execution to the FPM backend
#
<FilesMatch \.php$>
    SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
</FilesMatch
```

```
systemctl restart php-fpm
systemctl restart httpd
```


## Threaded MPM(Multi-Processing Module)

### What is Apache MPM 

Apache MPM(Multi-Processing Module) refers to the module in the Apache HTTP Server responsible for managing the creation and management of multiple processes or threads to handle incoming requests. The choice of MPM can significantly impact the performance and scalability of the web server.

#### 1. Prefork MPM
Prefork MPM launches multiple child processes. Each child process handle one connection at a time.

Prefork uses high memory in comparison to worker MPM. Prefork is the default MPM used by Apache server. Preform MPM always runs few minimum (MinSpareServers) defined processes as spare, so new requests do not need to wait for new process to start.

#### 2. Worker MPM
Worker MPM generates multiple child processes similar to prefork. Each child process runs many threads. Each thread handles one connection at a time.

In sort Worker MPM implements a hybrid multi-process multi-threaded server. Worker MPM uses low memory in comparison to Prefork MPM.

#### 3. Event MPM
Event MPM is introduced in Apache 2.4, It is pretty similar to worker MPM but it designed for managing high loads.

This MPM allows more requests to be served simultaneously by passing off some processing work to supporting threads. Using this MPM Apache tries to fix the ‘keep alive problem’ faced by other MPM. When a client completes the first request then the client can keep the connection open, and send further requests using the same socket, which reduces connection overload.

### Changing the default MPM in Apache

By default, the Apache HTTP Server uses a set of processes to manage incoming requests (prefork MPM).

As we now don't use mod_php we can switch to a threaded MPM (worker or an event) so a set of threads will manage the requests, reducing the number of running processes and the memory footprint, and improving performance, especially when a lot of static files are served.


Switch the used MPM in the `/etc/httpd/conf.modules.d/00-mpm.conf`

```
# disabled # LoadModule mpm_prefork_module modules/mod_mpm_prefork.so
# disabled # LoadModule mpm_worker_module modules/mod_mpm_worker.so
LoadModule mpm_event_module modules/mod_mpm_event.so
```

```
systemctl restart httpd
```

## Using Nginx(instead of Apache) with PHP-FPM/FastCGI

>Starting fresh at this point. Using Centos 7

```
yum install epel-release -y
yum repolist
yum install nginx -y
nginx -v
# Output: nginx version: nginx/1.20.1
systemctl start nginx
systemctl enable nginx

yum install php-fpm -y
php-fpm -v
# Output: PHP 5.4.16 (fpm-fcgi)
systemctl start php-fpm
systemctl enable php-fpm
```

- PHP-FPM configuration file: `/etc/php-fpm.d/www.conf`
```
[www]
listen = /run/php-fpm/www.sock
listen.allowed_clients = 127.0.0.1
listen.owner = nobody
listen.group = nobody
user = nginx
group = nginx
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
slowlog = /var/log/php-fpm/www-slow.log
php_admin_value[error_log] = /var/log/php-fpm/www-error.log
php_admin_flag[log_errors] = on
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/session
```


- Nginx configuration file: `/etc/nginx/conf.d/default.conf`
```
server {
    listen       80;
    server_name  localhost;

    # Declare the root globally here for all location
    #root   /usr/share/nginx/html

    location / {
        root   /usr/share/nginx/html;
        index  index.html index.htm;
        try_files $uri $uri/ =404;
    }

    error_page  404              /404.html;
    error_page   500 502 503 504  /50x.html;
    location = /50x.html {
        root   /usr/share/nginx/html;
    }

    # proxy the PHP scripts to Apache listening on 127.0.0.1:80
    #
    #location ~ \.php$ {
    #    proxy_pass   http://127.0.0.1;
    #}

    # pass the PHP scripts to FastCGI server listening on 127.0.0.1:9000
    #
    #location ~ \.php$ {
    #    root           html;
    #    fastcgi_pass   127.0.0.1:9000;
    #    fastcgi_index  index.php;
    #    fastcgi_param  SCRIPT_FILENAME  /scripts$fastcgi_script_name;
    #    include        fastcgi_params;
    #}

    # deny access to .htaccess files, if Apache's document root
    # concurs with nginx's one
    #
    #location ~ /\.ht {
    #    deny  all;
    #}

    location ~ \.php$ {
        # Can be hashed if root is declared globally above
	root   /usr/share/nginx/html;
        try_files $uri =404;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

```
echo "<?php phpinfo(); ?>" > /usr/share/nginx/html/info.php
systemctl restart php-fpm nginx
```

- `fastcgi.conf` & `fastcgi_params` file location: `/etc/nginx`

**Visit** `http://<my-ip>/info.php`


## Nginx with PHP-FPM/FastCGI in Ubuntu

> I am using Ubuntu 20.04.5 LTS

```
add-apt-repository ppa:ondrej/php
apt update
apt install php8.3-fpm -y
php -v
# Output: PHP 8.3.3-1+ubuntu20.04.1+deb.sury.org+1 (cli) (built: Feb 15 2024 18:38:21) (NTS)
apt install nginx -y
nginx -v
# Output: nginx version: nginx/1.18.0 (Ubuntu)
systemctl start php8.3-fpm
systemctl start nginx
```

- The main FPM configuration file is `/etc/php/8.3/fpm/php-fpm.conf`
- FPM pool configuration file is `/etc/php/8.3/fpm/pool.d/www.conf`


Let us create an FPM pool for running a PHP application effectively through a separate user. To start with, create a new user who will have exclusive rights over this pool.

```
groupadd info_user
useradd -g info_user info_user
```

Pool configuration file `/etc/php/8.3/fpm/pool.d/php-info.conf`

```
[php_info_pool]
user = info_user
group = info_user
listen = /var/run/php8.3-fpm-php-info-pool.sock
listen.owner = www-data
listen.group = www-data
php_admin_value[disable_functions] = exec,passthru,shell_exec,system
php_admin_flag[allow_url_fopen] = off
; Choose how the process manager will control the number of child processes. 
pm = dynamic 
pm.max_children = 75 
pm.start_servers = 10 
pm.min_spare_servers = 5 
pm.max_spare_servers = 20 
pm.process_idle_timeout = 10s
```

Nginx site configuration: `/etc/nginx/sites-enabled/default`

```
server {
	listen 80 default_server;
	listen [::]:80 default_server;
	
	root /var/www/html;

	index index.html index.htm index.nginx-debian.html;

	server_name _;

	location / {
		try_files $uri $uri/ =404;
	}
	
	location ~ \.php$ {
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass unix:/var/run/php8.3-fpm-php-info-pool.sock;
            fastcgi_index index.php;
            include fastcgi.conf;
    }
}
```

```
echo "<?php phpinfo(); ?>" > /var/www/html/info.php
systemctl restart php8.3-fpm
systemctl restart nginx
```

**Visit** `http://<my-ip>/info.php`


## What is `Supervisor` (A brief introduction)
**Supervisor** (also known as **supervisord)** is a process control system for Unix-like operating systems. It allows you to manage and control multiple processes, restart them if they crash, and monitor their status. Supervisor is often used in scenarios where you need to run multiple processes concurrently and ensure their availability and stability.

**Installing Supervisor**
> Ubuntu 20.04.5 LTS
```
apt install -y supervisor
systemctl start supervisor
systemctl enable supervisor
```

**Configuration files**

Supervisor Configuration file: `/etc/supervisor/supervisord.conf`

Worker Process Configuration directory: `/etc/supervisor/conf.d/`

**Why do we need Supervisor?**

Sometimes, we require a process to run indefinitely without interruption, such as scripts, scheduled tasks, or critical services like PHP-FPM/Nginx servers. Instead of simply running these processes in the background, we can use `Supervisor` to manage them. `Supervisor` ensures that even if the process stops unexpectedly for any reason, it will be automatically restarted. This ensures that the intended process remains consistently operational and reliable.


## Nginx with PHP FPM/FastCGI & Supervisor using Docker

>I am using Ubuntu 20.04.5 LTS



**Prepare the Server**
```
apt install docker.io docker-compose -y
systemctl start docker
systemctl enable docker
```
**Download the code repository**
```
git clone https://github.com/arindamgb/php-devops.git
cd php-devops/nginx-with-php-fpm-docker
```
**Build and run Docker images**
```
docker build -f Dockerfile_code -t arindamgb/php-code:1.0 .
docker build -f Dockerfile_phpfpm -t arindamgb/ubuntu22-php8.3-fpm:1.0 .
docker build -f Dockerfile_supervisor -t arindamgb/ubuntu22-supervisor:1.0 .
```
**Docker Compose File**

```
version: '3'

services:
  code:
    container_name: code
    image: arindamgb/php-code:1.0
    restart: always
    volumes:
      - code_volume:/app
  php-fpm:
    container_name: php-fpm
    image: arindamgb/ubuntu22-php8.3-fpm:1.0
    restart: always
    depends_on:
      - code
    volumes:
      - code_volume:/var/www/html:ro
      - ./php_pool_config:/etc/php/8.3/fpm/pool.d:ro
  nginx:
    container_name: nginx
    image: nginx:1.25.4-alpine
    restart: always
    depends_on:
      - code
      - php-fpm
    ports:
      - 8080:80
    volumes:
      - code_volume:/var/www/html:ro
      - ./nginx_config:/etc/nginx/conf.d/:ro
  worker-supervisor:
    container_name: worker-supervisor
    working_dir: /var/www/html
    image: arindamgb/ubuntu22-supervisor:1.0
    restart: always
    ports:
      - 9001:9001
    volumes:
      - code_volume:/var/www/html:rw
      - ./supervisor_config/supervisord.conf:/etc/supervisor/supervisord.conf:ro
      - ./supervisor_config/conf.d:/etc/supervisor/conf.d/:ro
    depends_on:
      - code
      - php-fpm
volumes:
  code_volume:
```


```
docker-compose up -d
```

**Visit**
```
http://<my-ip>:8080
http://<my-ip>:8080/info.php
```

**Check the log file of Supervisor Worker Process**

```
docker-compose exec -T  worker-supervisor  tail -f hello-world/hello.log
168 - Hello @ 19:25:05
169 - Hello @ 19:25:07
170 - Hello @ 19:25:09
171 - Hello @ 19:25:11
172 - Hello @ 19:25:13
173 - Hello @ 19:25:15
174 - Hello @ 19:25:17
```

**Check the `STDOUT` log of Supervisor Worker**

```
docker-compose exec -T  worker-supervisor  tail -f hello-world/hello-world_stdout.log
12 - Hello STDOUT @ 20:16:55
13 - Hello STDOUT @ 20:16:57
14 - Hello STDOUT @ 20:16:59
15 - Hello STDOUT @ 20:17:01
16 - Hello STDOUT @ 20:17:03
17 - Hello STDOUT @ 20:17:05
```

**Access Supervisor Web Interface**
```
http://<my-ip>:9001
User: admin
Password: admin123
```

![Supervisor Web Interface](/images/supervisor_web_interface.png "Supervisor Web Interface")


**Signing off, [Arindam Gustavo Biswas](https://www.linkedin.com/in/arindamgb/)**

21st March 2024, 02:43 AM
