%define cpan_name Net-Curl

Name:		perl-Net-Curl
Version:	0.44
Release:	1%{?dist}
Summary:	Perl interface for libcurl
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/Net::Curl
Source0:	https://cpan.metacpan.org/authors/id/S/SY/SYP/%{cpan_name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

Provides:       perl(Net::Curl)
Provides:       perl(Net::Curl::Compat)
Provides:       perl(Net::Curl::Easy)
Provides:       perl(Net::Curl::Form)
Provides:       perl(Net::Curl::Share)
Provides:       perl(Net::Curl::Multi)

BuildRequires:  make
BuildRequires:  gcc
BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  libcurl-devel

Requires:	perl
Requires:	libcurl
AutoReqProv:    no

%description
Net::Curl provides a Perl interface to libcurl created with object-oriented implementations in mind. This documentation contains Perl-specific details and quirks. For more information consult libcurl man pages and documentation at http://curl.haxx.se.

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
#%doc Changes
%{perl_vendorarch}/
%{_mandir}/man3/*.3*

%changelog

