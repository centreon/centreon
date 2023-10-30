%define cpan_name UUID

Name:		perl-UUID
Version:	0.28
Release:	1%{?dist}
Summary:	DCE compatible Universally Unique Identifier library for Perl
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/UUID
Source0:	https://cpan.metacpan.org/authors/id/J/JR/JRM/%{cpan_name}-%{version}.tar.gz
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  perl(Devel::CheckLib)
BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  libuuid-devel
BuildRequires:  make

Provides:	perl(UUID)
Requires:   libuuid
AutoReqProv:    no

%description
The UUID library is used to generate unique identifiers for objects that may be accessible beyond the local system. For instance, they could be used to generate unique HTTP cookies across multiple web servers without communication between the servers, and without fear of a name clash.

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
%{perl_vendorarch}
%{_mandir}/man3/*.3*

%changelog

