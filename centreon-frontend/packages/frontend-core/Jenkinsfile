stage('Source') {
  node {
    sh 'setup_centreon_build.sh'
    dir('eslint-config-centreon') {
      checkout scm
    }
    sh './centreon-build/jobs/eslint-config-centreon/eslint-config-centreon-source.sh'
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
  }
}

try {
  stage('Delivery') {
    node {
      sh 'setup_centreon_build.sh'
      sh './centreon-build/jobs/eslint-config-centreon/eslint-config-centreon-delivery.sh'
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Delivery stage failure.');
    }
  }
} catch(e) {
  if (env.BRANCH_NAME == 'master') {
    slackSend channel: "#monitoring-metrology",
      color: "#F30031",
      message: "*FAILURE*: `CENTREON ESLINT CONFIGURATION` <${env.BUILD_URL}|build #${env.BUILD_NUMBER}> on branch ${env.BRANCH_NAME}\n" +
        "*COMMIT*: <https://github.com/centreon/eslint-config-centreon/commit/${source.COMMIT}|here> by ${source.COMMITTER}\n" +
        "*INFO*: ${e}"
  }

  currentBuild.result = 'FAILURE'
}