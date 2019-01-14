stage('Source') {
  node {
    sh 'setup_centreon_build.sh'
    dir('centreon-react-components') {
      checkout scm
    }
    sh './centreon-build/jobs/react-components/react-components-source.sh'
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
  }
}

try {
  if (env.BRANCH_NAME == 'npm-publish') {
    stage('Delivery') {
      node {
        sh 'setup_centreon_build.sh'
        sh './centreon-build/jobs/react-components/react-components-delivery.sh'
      }
      if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
        error('Delivery stage failure.');
      }
    }
  }
} catch(e) {
  if (env.BRANCH_NAME == 'npm-publish') {
    slackSend channel: "#monitoring-metrology",
        color: "#F30031",
        message: "*FAILURE*: `CENTREON REACT COMPONENTS` <${env.BUILD_URL}|build #${env.BUILD_NUMBER}> on branch ${env.BRANCH_NAME}\n" +
            "*COMMIT*: <https://github.com/centreon/centreon-react-components/commit/${source.COMMIT}|here> by ${source.COMMITTER}\n" +
            "*INFO*: ${e}"
  }

  currentBuild.result = 'FAILURE'
}