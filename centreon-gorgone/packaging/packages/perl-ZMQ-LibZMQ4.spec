%define cpan_name ZMQ-LibZMQ4

Name:		perl-ZMQ-LibZMQ4
Version:	0.01
Release:	1%{?dist}
Summary:	A libzmq 4.x wrapper for Perl
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/ZMQ::LibZMQ4
Source0:	https://cpan.metacpan.org/authors/id/M/MO/MOSCONI/%{cpan_name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  gcc
BuildRequires:  make
BuildRequires:  perl(Devel::PPPort)
BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  zeromq-devel

Provides:	perl(ZMQ::LibZMQ4)
Requires:	perl
Requires:   zeromq
Requires:   perl(ExtUtils::ParseXS)
Requires:   perl(ZMQ::Constants)
AutoReqProv:    no

%description
The ZMQ::LibZMQ4 module is a wrapper of the 0MQ message passing library for Perl.

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
%{perl_vendorarch}/
%{_mandir}/man3/*.3*

%changelog

