/*
** Variables.
*/
properties([buildDiscarder(logRotator(numToKeepStr: '50'))])
def serie = '20.04'
def maintenanceBranch = "${serie}.x"
if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
} else if ((env.BRANCH_NAME == 'master') || (env.BRANCH_NAME == maintenanceBranch)) {
  env.BUILD = 'REFERENCE'
} else {
  env.BUILD = 'CI'
}

/*
** Pipeline code.
*/
stage('Source') {
  node {
    sh 'setup_centreon_build.sh'
    dir('centreon-ui') {
      checkout scm
    }
    sh "./centreon-build/jobs/ui/${serie}/ui-source.sh"
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
  }
}

try {
  stage('Unit tests') {
    node {
      sh 'setup_centreon_build.sh'
      sh "./centreon-build/jobs/ui/${serie}/ui-unittest.sh"
      junit 'ut.xml'
      recordIssues(
        enabledForFailure: true,
        tools: [esLint(pattern: 'codestyle.xml')],
        referenceJobName: 'centreon-ui/master'
      )
      archiveArtifacts allowEmptyArchive: true, artifacts: 'snapshots/*.png'
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Unit tests stage failure.');
    }
  }

  stage('Delivery') {
    node {
      sh 'setup_centreon_build.sh'
      sh './centreon-build/jobs/ui/${serie}/ui-delivery.sh'
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Delivery stage failure.');
    }
  }
} catch(e) {
  if (env.BRANCH_NAME == 'master') {
    slackSend channel: "#monitoring-metrology",
      color: "#F30031",
      message: "*FAILURE*: `CENTREON UI` <${env.BUILD_URL}|build #${env.BUILD_NUMBER}> on branch ${env.BRANCH_NAME}\n" +
        "*COMMIT*: <https://github.com/centreon/centreon-ui/commit/${source.COMMIT}|here> by ${source.COMMITTER}\n" +
        "*INFO*: ${e}"
  }

  currentBuild.result = 'FAILURE'
}
