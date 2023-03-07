%define cpan_name FFI-CheckLib

Name:		perl-FFI-CheckLib
Version:	0.31
Release:	1%{?dist}
Summary:	Check that a library is available for FFI
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/FFI::CheckLib
Source0:	https://cpan.metacpan.org/authors/id/P/PL/PLICEASE/%{cpan_name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

Provides:       perl(FFI::CheckLib)

BuildRequires:  make
BuildRequires:  perl(ExtUtils::MakeMaker)

Requires:       perl(File::Which)
Requires:       perl(List::Util)

%description
This module checks whether a particular dynamic library is available for FFI to use. It is modeled heavily on Devel::CheckLib, but will find dynamic libraries even when development packages are not installed. It also provides a find_lib function that will return the full path to the found dynamic library, which can be feed directly into FFI::Platypus or another FFI system.

%global debug_package %{nil}

%prep
%setup -q -n %{cpan_name}-%{version}

%build
%{__perl} Makefile.PL INSTALLDIRS=vendor OPTIMIZE="$RPM_OPT_FLAGS"
make %{?_smp_mflags}

%install
rm -rf %{buildroot}
make pure_install PERL_INSTALL_ROOT=$RPM_BUILD_ROOT
find $RPM_BUILD_ROOT -type f -name '*.bs' -a -size 0 -exec rm -f {} ';'
find $RPM_BUILD_ROOT -type f -name .packlist -exec rm -f {} ';'
find $RPM_BUILD_ROOT -type d -depth -exec rmdir {} 2>/dev/null ';'
%{_fixperms} $RPM_BUILD_ROOT/*

%check
#make test

%clean
rm -rf %{buildroot}

%files
%defattr(-,root,root,-)
%doc Changes
%{perl_vendorlib}/
%{_mandir}/man3/*.3*

%changelog

