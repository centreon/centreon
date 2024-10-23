# centreon-perl-libs / centreon-perl-libs-common

This directory contains the common Perl libraries used by Centreon.

## centreon-perl-libs-common

This package contain libraries located in centreon::common namespace.
they are generic library used by multiples centreon modules.

### tests

to execute the test, see t/ directory in the package.
First install the dependency : 
```bash
# distro package to install :  
openssl-dev
# cpan package to install :
Test2::V0 Test2::Plugin::NoWarnings Crypt::OpenSSL::AES
```

run the following command passing the path to t/ folder as argument :
```bash
prove -r t/
```


## centreon-perl-libs

This package contain libraries associated to specific binary, they are located in centreon::[area] namespace.

for exemple the package centreon-trap contain a binary calling centreon::trap namespace which contain all the logic.


