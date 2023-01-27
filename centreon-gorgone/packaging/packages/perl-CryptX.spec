%define cpan_name CryptX

Name:		perl-CryptX
Version:	0.068
Release:	1%{?dist}
Summary:	Cryptographic toolkit (self-contained, no external libraries needed)
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/CryptX
Source0:	https://cpan.metacpan.org/authors/id/M/MI/MIK/%{cpan_name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  make
BuildRequires:  gcc

%description
Cryptography in CryptX is based on https://github.com/libtom/libtomcrypt

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

