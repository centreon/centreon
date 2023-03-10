%define cpan_name ZMQ-FFI

Name:		perl-ZMQ-FFI
Version:	1.18
Release:	1%{?dist}
Summary:	version agnostic Perl bindings for zeromq using ffi
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/ZMQ::FFI
Source0:	https://cpan.metacpan.org/authors/id/G/GH/GHENRY/%{cpan_name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

Provides:       perl(ZMQ::FFI)

BuildRequires:  make
BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  zeromq-devel

Requires:       zeromq
Requires:       perl(FFI::CheckLib)
Requires:       perl(FFI::Platypus)
Requires:       perl(Moo)
Requires:       perl(Moo::Role)
Requires:       perl(Scalar::Util)
Requires:       perl(Try::Tiny)
Requires:       perl(namespace::clean)
Requires:       perl(Import::Into)

%description
ZMQ::FFI exposes a high level, transparent, OO interface to zeromq independent of the underlying libzmq version. Where semantics differ, it will dispatch to the appropriate backend for you. As it uses ffi, there is no dependency on XS or compilation.

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

