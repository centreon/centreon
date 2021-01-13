/*
** Variables.
*/
properties([buildDiscarder(logRotator(numToKeepStr: '50'))])
def serie = '21.04'
def maintenanceBranch = "${serie}.x"
if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
} else if ((env.BRANCH_NAME == 'master') || (env.BRANCH_NAME == maintenanceBranch)) {
  env.BUILD = 'REFERENCE'
} else {
  env.BUILD = 'CI'
}
def featureFiles = []

/*
** Pipeline code.
*/
stage('Source') {
  node {
    sh 'setup_centreon_build.sh'
    env.WIDGET = 'service-monitoring'
    dir("centreon-widget-${env.WIDGET}") {
      checkout scm
    }
    sh "./centreon-build/jobs/widgets/${serie}/widget-source.sh"
    source = readProperties file: 'source.properties'
    env.PROJECT = "${source.PROJECT}"
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
    env.SUMMARY = "${source.SUMMARY}"
    publishHTML([
      allowMissing: false,
      keepAll: true,
      reportDir: 'summary',
      reportFiles: 'index.html',
      reportName: 'Centreon Build Artifacts',
      reportTitles: ''
    ])
  }
}

try {
  stage('Unit tests') {
    parallel 'centos7': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/widgets/${serie}/widget-unittest.sh centos7"
        if (currentBuild.result == 'UNSTABLE')
          currentBuild.result = 'FAILURE'

        if (env.CHANGE_ID) { // pull request to comment with coding style issues
          ViolationsToGitHub([
            repositoryName: 'centreon-widget-service-monitoring',
            pullRequestId: env.CHANGE_ID,

            createSingleFileComments: true,
            commentOnlyChangedContent: true,
            commentOnlyChangedFiles: true,
            keepOldComments: false,

            commentTemplate: "**{{violation.severity}}**: {{violation.message}}",

            violationConfigs: [
              [parser: 'CHECKSTYLE', pattern: '.*/codestyle-be.xml$', reporter: 'Checkstyle']
            ]
          ])
        }

        discoverGitReferenceBuild()
        recordIssues(
          enabledForFailure: true,
          failOnError: true,
          qualityGates: [[threshold: 1, type: 'DELTA', unstable: false]],
          tool: phpCodeSniffer(id: 'phpcs', name: 'phpcs', pattern: 'codestyle-be.xml'),
          trendChartType: 'NONE'
        )

        if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
          withSonarQubeEnv('SonarQube') {
            sh "./centreon-build/jobs/widgets/${serie}/widget-analysis.sh"
          }
        }
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Unit tests stage failure.');
    }
  }

  stage('Package') {
    parallel 'centos7': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/widgets/${serie}/widget-package.sh centos7"
      }
    },
    'centos8': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/widgets/${serie}/widget-package.sh centos8"
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Package stage failure.');
    }
  }

  if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
    stage('Delivery') {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/widgets/${serie}/widget-delivery.sh"
      }
      if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
        error('Delivery stage failure.');
      }
    }
  }
} catch(e) {
  if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
    slackSend channel: "#monitoring-metrology",
        color: "#F30031",
        message: "*FAILURE*: `CENTREON WIDGET SERVICE MONITORING` <${env.BUILD_URL}|build #${env.BUILD_NUMBER}> on branch ${env.BRANCH_NAME}\n" +
            "*COMMIT*: <https://github.com/centreon/centreon-widget-service-monitoring/commit/${source.COMMIT}|here> by ${source.COMMITTER}\n" +
            "*INFO*: ${e}"
  }

  currentBuild.result = 'FAILURE'
}
