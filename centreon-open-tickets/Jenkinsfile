stage('Source') {
  node {
    sh 'setup_centreon_build.sh'
    dir('centreon-open-tickets') {
      checkout scm
    }
    sh './centreon-build/jobs/open-tickets/18.10/mon-open-tickets-source.sh'
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
  }
}

stage('Package') {
  parallel 'centos7': {
    node {
      sh 'setup_centreon_build.sh'
      sh './centreon-build/jobs/open-tickets/18.10/mon-open-tickets-package.sh centos7'
    }
  }
  if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
    error('Package stage failure.')
  }
}
