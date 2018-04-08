Bienvenue dans la documentation du module Centreon AWIE !
=========================================================

Le module Centreon AWIE a été conçu pour aider les utilisateurs à configurer plusieurs plateformes Centreon Web, d'une manière plus rapide et plus facile, grâce à son mécanisme d'import/export.

A partir d'un environnement source correctement configuré, on pourra utiliser le module d'Import/Export pour recréer les objets souhaités à l'identique dans un environnement cible.

Centreon AWIE s'appuie sur les commandes CLAPI mais possède l'avantage de pouvoir les exécuter depuis l'interface graphique au lieu d'avoir à les taper dans un terminal. Mais à l'instar de CLAPI, le module AWIE ne doit pas être utilisé pour apporter des modifications à des objets déjà existants dans la plateforme cible. Il n'est efficace que pour la création de nouveaux objets.  

Sommaire :

.. toctree::
   :maxdepth: 2

   Installation.rst
   Export.rst
   Import.rst
