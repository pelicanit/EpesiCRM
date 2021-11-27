---
description: 'About and setup instructions. For more info visit: https://epesi.org/'
cover: .gitbook/assets/epesi-linode-network.png
coverY: 0
---

# 🖥 epesi CRM

[![SourceForge](https://img.shields.io/sourceforge/dt/epesi.svg)](https://sourceforge.net/projects/epesi) SourceForge Repository

**Epesi BIM** (Business Information Manager) is a fully functional web CRM application to store, organize, access and share business records. Manage your data precisely, flexibly and easily, simplifying internal communication and making work-flow more efficient. It runs on any xAMP stack on all operating systems including Raspberry Pi. It is small, fast and can easily handle millions of records and hundreds of users.

Epesi has modular architecture and provides a great starting point for a full blown ERP system. You can use it as a **kickstarter** for your project as it already includes:

* Dashboard
* Admin Panel
* User Management
* Advanced CRUD engine
* CRM functionality
* Advanced Epesi File Storage
* Advanced Permissions system
* Unlimited users license
* and more...

As a developer you can control which files are installed by default using simple _distro.ini_ file, where you specify which modules should be setup on the first try. This way you can create your own Epesi distro and complete web application, which can be very easily deployed in the cloud.

**About**

EPESI BIM is a result of many years of experience working with SMB businesses and addresses inefficiencies of current e-mail “collaboration” workflow and commonplace data management using inadequate spreadsheet applications. It is a completely web based application designed for small and medium-sized enterprises trying to optimize business processes, simplify office work and reduce administrative costs. It does not require any client to be installed - any modern browser on any operating system will work - drastically reducing the deployment cost.

Our software can make your organization more efficient, better organized and more competitive. We can help you simplify and automate internal procedures with management of important business information.

* To eliminate e-mail in our workplace we internally use **EPESI** for all tasks, notes, projects and tickets - making our life more organized.
* In addition we use great multiplatform and programmable chat: **Telegram https://telegram.org/** available here at Github as well: https://github.com/telegramdesktop

Telegram is a messaging app with a focus on speed and security, it’s super-fast, simple and free. You can use Telegram on all your devices at the same time — your messages sync seamlessly across any number of your phones, tablets or computers.

* Epesi is already integrated with Telegram messaging platform te receive notifications from the **Watchdog** module (free and included with the latest release) as well as with Time tracking and reporting module integrated with Premium module Timesheets.

**Support**

* For users - please visit our forum http://forum.epe.si/ - to receive free technical assistance
* For developers - please open issues here: https://github.com/jtylek/issues
* For Premium Support - paid service provided by Epesi Dev Team - visit:\
  https://epesi.cloud/submitticket.php?step=2\&deptid=1

**Automatic Setup:**

* If you already have a hosting plan with cPanel then use Autoinstall via Softaculous:\
  https://www.softaculous.com/apps/erp/EPESI

Video tutorial on how to install epesi using Softaculous autoinstaller via cPanel\
https://www.youtube.com/watch?v=FR4mQsHUNCY

**DIY - Do It Yourself manual methods:**

For experienced users and server administrators: It requires properly configured HTTP server with PHP (ver 7.x) and MySQL/MariaDB database server - so called LAMP stack: https://en.wikipedia.org/wiki/LAMP\_(software\_bundle)

* Download ready to run package from Sourceforge: http://sourceforge.net/projects/epesi
* Use Easy Install Script: http://sourceforge.net/projects/epesi/files/easy%20installer/
* Use git and clone this repository: https://github.com/jtylek/epesi

If using a package from SourceForge all vendors libraries are already included. Just point your browser to location where your Epesi was installed and unpacked and the setup will start. You have to create a database and database user in a separate step. unless you have root access.

If using Git repository then you must run **composer update** to download libraries into vendor directory. Make sure that you run composer update after every update from repository as dependencies may change.

`git clone https://github.com/jtylek/epesi.git your_epesi_dir`

`cd your_epesi_dir`

`composer update`

Enjoy,

Janusz Tylek

https://epe.si

***

**License:**

EPESI is released under the MIT License

**Copyright © 2006-2020 by Janusz Tylek**

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/orsell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

**The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.**

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHERDEALINGS IN THE SOFTWARE.

**By installing and using this software you automatically agree with the licensing terms and included EULA** (End User License Agreement)

[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2Fjtylek%2Fepesi.svg?type=large)](https://app.fossa.com/projects/git%2Bgithub.com%2Fjtylek%2Fepesi?ref=badge\_large)
