%define cpan_name ZMQ-Constants

Name:		perl-ZMQ-Constants
Version:	1.04
Release:	1%{?dist}
Summary:	Constants for libzmq
Group:		Development/Libraries
License:	GPL or Artistic
URL:		http://search.cpan.org/~dmaki/ZMQ-Constants-1.04/lib/ZMQ/Constants.pm
Source0:	http://search.cpan.org/CPAN/authors/id/D/DM/DMAKI/%{cpan_name}-%{version}.tar.gz
BuildArch:  noarch
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  make
BuildRequires:  perl(ExtUtils::MakeMaker)

Provides:	perl(ZMQ::Constants)
Requires:	perl
AutoReqProv:    no

%description
libzmq is a fast-chanding beast and constants get renamed, new one gest removed, etc...

We used to auto-generate constants from the libzmq source code, but then adpating the binding code to this change got very tedious, and controling which version contains which constants got very hard to manage.

This module is now separate from ZMQ main code, and lists the constants statically. You can also specify which set of constants to pull in depending on the zmq version.

%prep
%setup -q -n %{cpan_name}-%{version}

%build
%{__perl} Makefile.PL INSTALLDIRS=vendor OPTIMIZE="$RPM_OPT_FLAGS"
make %{?_smp_mflags}

%install
rm -rf %{buildroot}
make pure_install PERL_INSTALL_ROOT=$RPM_BUILD_ROOT
find $RPM_BUILD_ROOT -type f -name .packlist -exec rm -f {} ';'
find $RPM_BUILD_ROOT -type f -name '*.bs' -a -size 0 -exec rm -f {} ';'
find $RPM_BUILD_ROOT -type d -depth -exec rmdir {} 2>/dev/null ';'
%{_fixperms} $RPM_BUILD_ROOT/*

%check
#make test

%clean
rm -rf %{buildroot}

%files
%defattr(-,root,root,-)
%doc Changes
%{perl_vendorlib}/ZMQ/
%{_mandir}/man3/*.3*

%changelog

