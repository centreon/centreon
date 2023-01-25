%define cpan_name Libssh-Session

Name:		perl-Libssh-Session
Version:	0.9
Release:	1%{?dist}
Summary:	perl interface to the libssh library
Group:		Development/Libraries
License:	Apache
URL:		https://metacpan.org/release/QGARNIER/Libssh-Session-0.8
Source0:    %{name}.tar.gz
#Source0:	http://search.cpan.org/CPAN/authors/id/M/ML/MLEHMANN/%{cpan_name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  libssh-devel
BuildRequires:  make
BuildRequires:  gcc
BuildRequires:  perl-ExtUtils-MakeMaker

Provides:	    perl(Libssh::Session)
Provides:	    perl(Libssh::Sftp)
Requires:	    libssh >= 0.9.0
AutoReqProv:    no

%description


%prep
%setup -q -n %{name}

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
%{perl_vendorarch}/
%{_mandir}/man3/*.3*

%changelog
