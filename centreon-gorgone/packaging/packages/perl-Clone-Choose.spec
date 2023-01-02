%define cpan_name Clone-Choose

Name:		perl-Clone-Choose
Version:	0.010
Release:	1%{?dist}
Summary:	Choose appropriate clone utility
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/Clone::Choose
Source0:	https://cpan.metacpan.org/authors/id/H/HE/HERMES/%{cpan_name}-%{version}.tar.gz
BuildArch:  noarch
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  make

Provides:	perl(Clone::Choose)
AutoReqProv:    no

%description
Clone::Choose checks several different modules which provides a clone() function and selects an appropriate one.

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

