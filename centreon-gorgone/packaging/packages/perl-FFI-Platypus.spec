%define cpan_name FFI-Platypus

Name:           perl-FFI-Platypus
Version:        2.05
Release:        1%{?dist}
Summary:        Write Perl bindings to non-Perl libraries with FFI. No XS required.
Group:          Development/Libraries
License:        GPL or Artistic
URL:            https://metacpan.org/pod/FFI::Platypus
Source0:        https://cpan.metacpan.org/authors/id/P/PL/PLICEASE/%{cpan_name}-%{version}.tar.gz
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  make
BuildRequires:  gcc
BuildRequires:  libffi-devel
BuildRequires:  perl(ExtUtils::MakeMaker)

Provides:       perl(FFI::Platypus)

Requires:       libffi
Requires:       perl(JSON::PP)
Requires:       perl(FFI::CheckLib)
Requires:       perl(Capture::Tiny)

%description
Platypus is a library for creating interfaces to machine code libraries written in languages like C, C++, Go, Fortran, Rust, Pascal. Essentially anything that gets compiled into machine code. This implementation uses libffi to accomplish this task. libffi is battle tested by a number of other scripting and virtual machine languages, such as Python and Ruby to serve a similar role.

%prep
%setup -q -n %{cpan_name}-%{version}

%build
export ODBCHOME=/usr/
export PERL_MM_USE_DEFAULT="1"
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

