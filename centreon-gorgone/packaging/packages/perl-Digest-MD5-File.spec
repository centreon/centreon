%define cpan_name Digest-MD5-File

Name:		Digest-MD5-File
Version:	0.08
Release:	1%{?dist}
Summary:	Perl extension for getting MD5 sums for files and urls.
Group:		Development/Libraries
License:	GPL or Artistic
URL:		https://metacpan.org/pod/Digest::MD5::File
Source0:	https://cpan.metacpan.org/authors/id/D/DM/DMUEY/%{cpan_name}-%{version}.tar.gz
BuildArch:  noarch
BuildRoot:	%{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  perl(ExtUtils::MakeMaker)
BuildRequires:  make

Provides:	perl(Digest::MD5::File)
Requires:   perl(Digest::MD5)
Requires:   perl(LWP::UserAgent)
AutoReqProv:    no

%description
Get MD5 sums for files of a given path or content of a given url.

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

