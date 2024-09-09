FROM hashicorp/vault:latest

RUN bash -e <<EOF

  apk add --no-cache jq
  apk add --no-cache openrc
  vault login ${VAULT_DEV_ROOT_TOKEN_ID}
  export VAULT_ADDR='http://127.0.0.1:8200'
  vault secrets enable -path=centreon kv
  vault auth enable approle

  mkdir /etc/vault.d
  cat <<EOF >>/etc/vault.d/central_policy.hcl
  # central
  path "centreon/*" {
    capabilities = ["create", "read", "update", "patch", "delete", "list"]
  }
  EOF

  vault policy write central /etc/vault.d/central_policy.hcl
  vault write auth/approle/role/central token_policies="central" \
    token_ttl=1h token_max_ttl=4h

  export VAULT_ROLE_ID=$(vault read auth/approle/role/central/role-id -format=json | jq -r '.data.role_id')
  echo $VAULT_ROLE_ID >> /etc/vault.d/agent_role_id
  export VAULT_SECRET_ID=$(vault write -force auth/approle/role/central/secret-id -format=json | jq -r '.data.secret_id')
  echo $VAULT_SECRET_ID >> /etc/vault.d/agent_secret_id
  vault write auth/approle/login role_id=$VAULT_ROLE_ID secret_id=$VAULT_SECRET_ID

  cat <<EOF>/etc/vault.d/vault-agent.hcl
  vault {
    address = "http://127.0.0.1:8200"
    retry {
      num_retries = 5
    }
  }

  auto_auth {
    method {
      type = "approle"
      config = {
        role_id_file_path = "/etc/vault.d/agent_role_id"
        secret_id_file_path = "/etc/vault.d/agent_secret_id"
        remove_secret_id_file_after_reading = false
      }
    }
    sink "file" {
      config = {
        path = "/tmp/vault-agent"
      }
    }
  }

  cache {
    use_auto_auth_token = true
  }

  listener "tcp" {
    address = "127.0.0.1:8100"
    tls_disable = true
  }

  listener "unix" {
    address = "/tmp/vault-agent.sock"
    tls_disable = true
    socket_user = "vault"
    socket_group = "vault"
    socket_mode = "0660"
  }
  EOF

  cat <<EOF >>/etc/init.d/vault-agent
    #!/sbin/openrc-run

    description="Vault Agent"
    command="/usr/bin/vault"
    command_args="agent -config=/etc/vault.d/vault-agent.hcl"
    command_user="vault:vault"
    retry="SIGTERM/5"

    depend() {
        after vault
        need vault
    }

    start_pre() {
        ebegin "Setting up Vault Agent pre-start"
        eend 0
    }

    reload() {
        ebegin "Reloading Vault Agent"
        start-stop-daemon --signal HUP --pidfile /run/vault-agent.pid
        eend $?
    }

    stop() {
        ebegin "Stopping Vault Agent"
        start-stop-daemon --stop --exec $command
        eend $?
    }
  EOF
EOF
