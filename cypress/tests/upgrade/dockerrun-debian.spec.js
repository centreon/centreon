describe("Execute Shellscript tests", () => {

    let docker_exec = "docker exec -i cy-test-1 /bin/sh -c ";

    context("Prepare env", () => {

        it("Return the current directory", () =>{
            cy.exec('pwd')
            .its('code').should('eq', 0)
        })

        it("Run Docker", () => {
            cy.exec('if [ $(docker ps -a| grep cy-test-1) ]; then docker rm -f cy-test-1; fi')
            cy.exec("docker run -itd --name cy-test-1 debian:11")
            .its('code')
            .should('eq', 0)
        })

    })

    context("Run commands inside docker", () => {

        it("run upgrade", () => {
            cy.exec(docker_exec + "'apt update'")
            .its('code').should('eq', 0)
            cy.exec(docker_exec + "'apt upgrade -y'")
            .its('code').should('eq', 0)
        })

        it("Install dependencies", () => {
            cy.exec(docker_exec + "'apt install -y curl sudo lsb-release ca-certificates apt-transport-https software-properties-common wget gnupg2'")
            .its('code').should('eq', 0)
        })

        it("Add Sury APT repository for PHP 8.1", () => {
            cy.exec(docker_exec + "'echo \"deb https://packages.sury.org/php/ $(lsb_release -sc) main\" | tee /etc/apt/sources.list.d/sury-php.list'")
            .its('code').should('eq', 0)
            cy.exec(docker_exec + "'wget -O- https://packages.sury.org/php/apt.gpg | gpg --dearmor | tee /etc/apt/trusted.gpg.d/php.gpg  > /dev/null 2>&1 && apt update'")
            .its('code').should('eq', 0)
        })

        it("MariaDB Repository", () => {
            cy.exec(docker_exec + "'curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup | sudo bash -s -- --os-type=debian --os-version=11 --mariadb-server-version=\"mariadb-10.5\"'")
            .its('code').should('eq', 0)
        })

        it("add Centreon Repository", () => {
            cy.exec(docker_exec + "'echo \"deb https://apt.centreon.com/repository/22.10/ $(lsb_release -sc) main\" | tee /etc/apt/sources.list.d/centreon.list'")
            .its('code').should('eq', 0)
            cy.exec(docker_exec + "'wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1 && apt update'")
            .its('code').should('eq', 0)
        })
    })

    context("Centreon Installation", () => {

        it("Install packages for Centreon", () => {
            cy.exec(docker_exec + "'apt install -y centreon centreon-central'", { timeout: 600000 })
            .its('code').should('eq', 0)
            cy.exec(docker_exec + "'systemctl daemon-reload && systemctl restart mariadb'")
            .its('code').should('eq', 0)
        })

    })
})
