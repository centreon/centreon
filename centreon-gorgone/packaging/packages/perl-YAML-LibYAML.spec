%define cpan_name YAML-LibYAML

Name:		perl-YAML-LibYAML
Version:	0.80
Release:	1%{?dist}
Summary:	Perl YAML Serialization using XS and libyaml
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/release/YAML-LibYAML
Source0:	https://cpan.metacpan.org/authors/id/T/TI/TINITA/%{cpan_name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  make
BuildRequires:  gcc

%description
Kirill Simonov's libyaml is arguably the best YAML implementation. The C library is written precisely to the YAML 1.1 specification. It was originally bound to Python and was later bound to Ruby.
This module is a Perl XS binding to libyaml which offers Perl the best YAML support to date.

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
%{perl_vendorarch}
%{_mandir}/man3/*.3*

%changelog

