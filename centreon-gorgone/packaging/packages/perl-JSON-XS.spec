%define cpan_name JSON-XS

Name:		perl-JSON-XS
Version:	4.02
Release:	1%{?dist}
Summary:	JSON serialising/deserialising, done correctly and fast
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/JSON::XS
Source0:	https://cpan.metacpan.org/authors/id/M/ML/MLEHMANN/%{cpan_name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  perl(Canary::Stability)
BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  make

Provides:	perl(JSON::XS)
Requires:   perl(common::sense)
Requires:   perl(Types::Serialiser)
AutoReqProv:    no

%description
This module converts Perl data structures to JSON and vice versa. Its primary goal is to be correct and its secondary goal is to be fast. To reach the latter goal it was written in C.

%prep
%setup -q -n %{cpan_name}-%{version}

%build
export PERL_CANARY_STABILITY_NOPROMPT=1
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
%{_usr}/bin/*
%{perl_vendorarch}
%{_mandir}

%changelog

