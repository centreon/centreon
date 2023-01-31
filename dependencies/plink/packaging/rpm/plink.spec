Name:           plink
Version:        0.74
Release:        1
Summary:        Plink (PuTTY Link) is a command-line connection tool similar to UNIX ssh.

Group:          Applications/System
License:        GPLv2
URL:            https://www.centreon.com
Packager:       Centreon <contact@centreon.com>
Vendor:         Centreon Entreprise Server (CES) Repository, http://yum.centreon.com/standard/

Source0:        plink
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root

Requires:       glibc

%description
Plink (PuTTY Link) is a command-line connection tool similar to UNIX ssh.
It is mostly used for automated operations, such as making CVS access a repository on a remote server.

%prep

%build

%install
%{__install} -d -m 0755 %buildroot%{_bindir}
%{__install} -m 0755 %SOURCE0 %buildroot%{_bindir}/plink

%clean
%{__rm} -rf $RPM_BUILD_ROOT

%files
%defattr(-,root,root,-)
%{_bindir}/plink

%changelog
