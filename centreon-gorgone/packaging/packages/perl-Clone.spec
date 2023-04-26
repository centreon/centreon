%define cpan_name Clone

Name:		perl-Clone
Version:	0.45
Release:	1%{?dist}
Summary:	recursively copy Perl datatypes
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/Clone
Source0:	https://cpan.metacpan.org/authors/id/A/AT/ATOOMIC/%{cpan_name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  gcc
BuildRequires:  make

Provides:	perl(Clone)
AutoReqProv:    no

%description
This module provides a clone() method which makes recursive copies of nested hash, array, scalar and reference types, including tied variables and objects.

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
%{perl_vendorarch}
%{_mandir}/man3/*.3*

%changelog

