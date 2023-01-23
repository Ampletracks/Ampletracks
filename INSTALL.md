HOW TO INSTALL AMPLETRACKS
==========================

Ampletracks runs on a standard LAMP stack i.e. Linux, Apache, MySQL and PHP

Whilst there is no reason why Ampletracks will not run on any standard Linux distribution, we only
have documentation in place at this stage relating to Debian. We are working on documenting other
distributions and OS's.

The installation process for Debian has been automated using [Ansible](https://github.com/ansible/ansible).

Once you have the prerequisites in place installing Ampletracks using Ansible will typically take
under 5 minutes.

Prerequisites
-------------

- A machine with Ansible installed on it. This will typically be your workstation,
  but there is nothing to stop this being the server you plan to install Ampletracks onto.
  
  See https://docs.ansible.com/ansible/latest/installation_guide/index.html

- Git installed on the same machine as you installed Ansible on.

    See https://git-scm.com/downloads

- A server running Debian 11 or Debian 10.

    See https://www.debian.org/download
  
  If you are using one of the major cloud hosting providers there will usually be a pre-built
  server image you can install e.g.
    - https://aws.amazon.com/marketplace/pp/prodview-l5gv52ndg5q6i
    - https://console.cloud.google.com/marketplace/product/debian-cloud/debian-bullseye
    - https://azuremarketplace.microsoft.com/en-us/marketplace/apps/debian.debian-11?tab=PlansAndPrice&exp=ubp8

- Shared keys in place to log in to this server.

  If you need some pointers on how to do that there are many online guides.
  e.g.https://www.digitalocean.com/community/tutorials/how-to-set-up-ssh-keys-on-debian-11

- [Optional, but required if you want to use Let's Encrypt for SSL certificates - which is recommended]

  DNS in place for the domain name you plan to host your Ampletracks site on. This will
  typically be an "A" record from the domain name pointing to the IP address of the server where
  Ampletracks will be installed. The TTL (time to live) for this DNS record is up to you - the
  longer you make it (e.g. 86400 seconds i.e 1 day) the longer it will take any future
  changes you make to this DNS record to propagate, but making it shorter (e.g. 600 seconds i.e. 10
  minutes) might end up increasing the cost charged by your DNS provider.
  DNS may well be handled by your IT Department, or Web Agency.
  
  The steps required to do this will vary depending on your DNS provider but here are some examples:
    - https://docs.aws.amazon.com/Route53/latest/DeveloperGuide/resource-record-sets-creating.html
    - https://cloud.google.com/dns/docs/records
    - https://uk.godaddy.com/help/add-an-a-record-19238
    - https://docs.digitalocean.com/products/networking/dns/how-to/manage-records/

Installation Steps
------------------

1. Check out the Ampletracks codebase
	~~~
	# Change directory to your home directory (optional)
	cd ~
	# Clone the Ampletracks repository
	git clone https://github.com/Ampletracks/Ampletracks.git
	# Change directory to the Ansible install tools
    cd Ampletracks/install/ansible
	~~~
2. Copy and edit the sample inventory file
	~~~
	# Copy the sample inventory file
	cp example.inventory.yml inventory.yml
	# Use your favourite editor to edit this file e.g.
	# The following line will use your default editor
	# or Pico if no default editor has be chosen.
	"${EDITOR:-pico}" inventory.yml
	~~~

3. Run the Ansible playbook
    ~~~
    ansible-playbook -i testInventory.yml install.yml
    ~~~

