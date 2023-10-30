%define cpan_name common-sense

Name:		perl-common-sense
Version:	3.75
Release:	1%{?dist}
Summary:	save a tree AND a kitten, use common::sense!
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/common::sense
Source0:	https://cpan.metacpan.org/authors/id/M/ML/MLEHMANN/%{cpan_name}-%{version}.tar.gz
BuildArch:  noarch
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  make

Provides:	perl(common::sense)
AutoReqProv:    no

%description
This module implements some sane defaults for Perl programs, as defined by two typical (or not so typical - use your common sense) specimens of Perl coders. In fact, after working out details on which warnings and strict modes to enable and make fatal, we found that we (and our code written so far, and others) fully agree on every option, even though we never used warnings before, so it seems this module indeed reflects a "common" sense among some long-time Perl coders.

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
%{perl_vendorlib}
%{_mandir}/man3/*.3*

%changelog

