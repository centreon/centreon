%define cpan_name Hash-Merge

Name:		perl-Hash-Merge
Version:	0.300
Release:	1%{?dist}
Summary:	Merges arbitrarily deep hashes into a single hash
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/Hash::Merge
Source0:	https://cpan.metacpan.org/authors/id/R/RE/REHSACK/%{cpan_name}-%{version}.tar.gz
BuildArch:  noarch
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  make

Provides:	perl(Hash::Merge)
Requires:   perl(Scalar::Util)
Requires:   perl(Clone::Choose)
AutoReqProv:    no

%description
Hash::Merge merges two arbitrarily deep hashes into a single hash. That is, at any level, it will add non-conflicting key-value pairs from one hash to the other, and follows a set of specific rules when there are key value conflicts (as outlined below). The hash is followed recursively, so that deeply nested hashes that are at the same level will be merged when the parent hashes are merged. Please note that self-referencing hashes, or recursive references, are not handled well by this method.

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

