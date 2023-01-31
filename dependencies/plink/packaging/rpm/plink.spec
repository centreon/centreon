%define archive_name putty

Name:       plink
Version:    0.74
Release:    1%{?dist}
Summary:    Plink (PuTTY Link) is a command-line connection tool similar to UNIX ssh.

Group:      Development/Tools
License:    MIT licence
URL:        http://www.chiark.greenend.org.uk/~sgtatham/putty/

Source0:    %{archive_name}-%{version}.tar.gz
BuildRoot:  %(mktemp -ud %{_tmppath}/%{archive_name}-%{version}-%{release}-XXXXXX)

BuildRequires:  make
BuildRequires:  gcc

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
