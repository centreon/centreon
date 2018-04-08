############
Installation
############

Centreon recommande d'utiliser ses paquets officiels.

**Utiliser les paquets**

Centreon fournit les fichiers RPM pour ses produits à travers la solution Centreon Entreprise Server (CES). Les produits Open source sont librement accessibles depuis notre dépôt.

Ces paquets sont disponibles pour CentOS 6 et Centos7.

Installer les paquets
=====================

Exécutez les commandes suivantes en tant qu'utilisateur avec des droits suffisants.


Pour CentOS 6::

  $ yum install centreon-awie-1.0.0.el6.noarch.rpm

Pour CentOS 7::

  $ yum install centreon-awie-1.0.0.el7.noarch.rpm

Toutes les dépendances seront automatiquement installées à partir des dépôts Centreon.

Installation de l'interface graphique utilisateur
=================================================

Connectez-vous à vos plateformes Centreon Web (une source, une cible).

Allez dans le menu Administration > Extensions > Modules.

Cliquez sur la molette "Installer module" à la fin de la ligne centreon-awie :

.. image:: _static/images/Install_awie.png
   :align: center

Cliquez sur le bouton *Install module* : 

.. image:: _static/images/installmodule.png
   :align: center

Cliquez sur le bouton *Retour* pour terminer l'installation : 

.. image:: _static/images/installback.png
   :align: center

