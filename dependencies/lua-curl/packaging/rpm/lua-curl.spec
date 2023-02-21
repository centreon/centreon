%define luaversion %(echo `pkg-config --variable=V lua`)
%define lualibdir %{_libdir}/lua/%{luaversion}
%define luadatadir %{_datadir}/lua/%{luaversion}

%define lualibopts %(echo `pkg-config --libs lua`)

%define real_version 0.3.11
%define real_package_name Lua-cURLv3

Summary: Lua binding to libcurl
Name: lua-curl
Version: 3.0.11
Release: 2%{?dist}
License: MIT
Group: Applications/Development
URL: https://github.com/Lua-cURL/Lua-cURLv3

Source: https://github.com/Lua-cURL/Lua-cURLv3/archive/v0.3.11.tar.gz
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root

BuildRequires: gcc
BuildRequires: libcurl-devel
BuildRequires: lua-devel >= 5.1
BuildRequires: make
BuildRequires: pkgconfig
Requires: lua >= 5.1
Requires: libcurl

%description
Lua binding to libcurl

%prep
%setup -n %{real_package_name}-%{real_version}

%build
%{__make} %{?_smp_mflags} CFLAGS="%{optflags} -fPIC"

%install
%{__rm} -rf %{buildroot}
%{__install} -Dp -m0755 lcurl.so %{buildroot}%{lualibdir}/lcurl.so
%{__install} -Dp -m0644 src/lua/cURL.lua %{buildroot}%{luadatadir}/cURL.lua
%{__install} -d -m 0755 %{buildroot}%{luadatadir}/cURL
%{__install} -Dp -m0644 src/lua/cURL/safe.lua %{buildroot}%{luadatadir}/cURL/safe.lua
%{__install} -Dp -m0644 src/lua/cURL/utils.lua %{buildroot}%{luadatadir}/cURL/utils.lua
%{__install} -d -m 0755 %{buildroot}%{luadatadir}/cURL/impl
%{__install} -Dp -m0644 src/lua/cURL/impl/cURL.lua %{buildroot}%{luadatadir}/cURL/impl/cURL.lua

%clean
%{__rm} -rf %{buildroot}

%files
%defattr(-, root, root, 0755)
%{lualibdir}/*
%{luadatadir}/*

%changelog
