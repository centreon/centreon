%define cpan_name Types-Serialiser

Name:		perl-Types-Serialiser
Version:	1.0
Release:	1%{?dist}
Summary:	simple data types for common serialisation formats
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/Types::Serialiser
Source0:	https://cpan.metacpan.org/authors/id/M/ML/MLEHMANN/%{cpan_name}-%{version}.tar.gz
BuildArch:  noarch
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  make

Provides:	perl(Types::Serialiser)
Requires:   perl(common::sense)
AutoReqProv:    no

%description
This module provides some extra datatypes that are used by common serialisation formats such as JSON or CBOR. The idea is to have a repository of simple/small constants and containers that can be shared by different implementations so they become interoperable between each other.

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
%{perl_vendorlib}
%{_mandir}/man3/*.3*

%changelog

