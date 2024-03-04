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
yum install php php-mysql -y
systemctl restart httpd

httpd -M | grep -i php
php5_module (shared)

echo "<?php phpinfo(); ?>" > /var/www/html/info.php
```

**Visit:** `http://<my-ip>/info.php`

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
