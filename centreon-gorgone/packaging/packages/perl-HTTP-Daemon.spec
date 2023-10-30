%define cpan_name HTTP-Daemon

Name:		perl-HTTP-Daemon
Version:	6.06
Release:	1%{?dist}
Summary:	A simple http server class
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/HTTP::Daemon
Source0:	https://cpan.metacpan.org/authors/id/O/OA/OALDERS/%{cpan_name}-%{version}.tar.gz
BuildArch:  noarch
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  perl(Module::Build::Tiny)
BuildRequires:  make

Provides:	perl(HTTP::Daemon)
Requires:   perl(HTTP::Date)
Requires:   perl(HTTP::Message)
Requires:   perl(HTTP::Response)
Requires:   perl(HTTP::Status)
Requires:   perl(LWP::MediaTypes)
AutoReqProv:    no

%description
Instances of the HTTP::Daemon class are HTTP/1.1 servers that listen on a socket for incoming requests. The HTTP::Daemon is a subclass of IO::Socket::IP, so you can perform socket operations directly on it too.

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

