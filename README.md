<a name="readme-top"></a>

[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Docker][docker-shield]][docker-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![MIT License][license-shield]][license-url]

<!-- ABOUT THE PROJECT -->
## About The Project

![Dashboard][screenshot-dashboard]
![DMARC-Reports][screenshot-dmarc]
![SMTP-TLS-Reports][screenshot-smtptls]

I needed a quick and easy way to view my dmarc and smtp tls reports.
This is a simple symfony project that can read the inbox for new reports and process them.
I you use it you should use it with a special sole-purpose email address.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- GETTING STARTED -->
## Getting Started

Setup is pretty easy 

### Prerequisites

* composer
* mariadb (10.5+)/postgresql (16.1+)/sqlite
* php (8.2+)
* php-ctype
* php-dom
* php-fileinfo
* php-fpm
* php-iconv
* php-imap
* php-mbstring
* php-pdo
* php-pdo-mysql
* php-pdo-pgsql
* php-pdo-sqlite
* php-phar
* php-session
* php-simplexml
* php-tokenizer
* php-xml
* php-xmlwriter
* php-zip

### Installation

1. Clone the repo
   ```sh
   git clone https://github.com/antedebaas/DMARC-SMTPTLS-Reports.git
   ```
2. run update.sh to update project, clear its cache, this prevents symfony caching issues
   ```sh
   bash [root path of this project]/update.sh
   ```
3. point the webserver root to the public/ directory
4. point your webbrowser to http(s)://[yourhost]/setup and follow instructions
5. run installservice.sh to install the systemd service and timer for automated mail checking
   ```sh
   bash [root path of this project]/installservice.sh
   ```
6. if you put it on https://mta-sts.yourmdomain.ext it will provide an mta-sts policy file (https://mta-sts.yourmdomain.ext/.well-known/mta-sts.txt)
   you can edit the policy on the domain edit page.


<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Docker

1. see the docker-compose.yml file for all variables and an example stack.
2. `MAILCHECK_SCHEDULE` can be adjusted to check the mailbox more or less frequent using a cron syntax.
   you can also use one of the following keywords: monthly, weekly, daily, hourly, 15min
3. run `docker compose up`

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTRIBUTING -->
## Contributing

Contributions are what make the open source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

If you have a suggestion that would make this better, please fork the repo and create a pull request. You can also simply open an issue with the tag "enhancement".
Don't forget to give the project a star! Thanks again!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- LICENSE -->
## License

Distributed under the GPL v2 License. See `LICENSE.txt` for more information.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- MARKDOWN LINKS & IMAGES -->
[contributors-shield]: https://img.shields.io/github/contributors/antedebaas/DMARC-SMTPTLS-Reports.svg?style=for-the-badge
[contributors-url]: https://github.com/antedebaas/DMARC-SMTPTLS-Reports/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/antedebaas/DMARC-SMTPTLS-Reports.svg?style=for-the-badge
[forks-url]: https://github.com/antedebaas/DMARC-SMTPTLS-Reports/network/members
[stars-shield]: https://img.shields.io/github/stars/antedebaas/DMARC-SMTPTLS-Reports.svg?style=for-the-badge
[stars-url]: https://github.com/antedebaas/DMARC-SMTPTLS-Reports/stargazers
[issues-shield]: https://img.shields.io/github/issues/antedebaas/DMARC-SMTPTLS-Reports.svg?style=for-the-badge
[issues-url]: https://github.com/antedebaas/DMARC-SMTPTLS-Reports/issues
[license-shield]: https://img.shields.io/github/license/antedebaas/DMARC-SMTPTLS-Reports.svg?style=for-the-badge
[license-url]: https://github.com/antedebaas/DMARC-SMTPTLS-Reports/blob/master/LICENSE.txt
[docker-shield]: https://img.shields.io/docker/pulls/antedebaas/dmarc-reports.svg?style=for-the-badge
[docker-url]: https://hub.docker.com/repository/docker/antedebaas/dmarc-reports/general
[screenshot-dashboard]: screenshot-dashboard.png
[screenshot-dmarc]: screenshot-dmarc.png
[screenshot-smtptls]: screenshot-smtptls.png
