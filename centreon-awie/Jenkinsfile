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

/*
** Pipeline code.
*/
stage('Source') {
  node {
    sh 'setup_centreon_build.sh'
    dir('centreon-awie') {
      checkout scm
    }
    sh "./centreon-build/jobs/awie/${serie}/mon-awie-source.sh"
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
    publishHTML([
      allowMissing: false,
      keepAll: true,
      reportDir: 'summary',
      reportFiles: 'index.html',
      reportName: 'Centreon AWIE Build Artifacts',
      reportTitles: ''
    ])
  }
}

try {
  stage('Unit tests') {
    parallel 'centos7': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/awie/${serie}/mon-awie-unittest.sh centos7"
        if (currentBuild.result == 'UNSTABLE')
          currentBuild.result = 'FAILURE'

        if (env.CHANGE_ID) { // pull request to comment with coding style issues
          ViolationsToGitHub([
            repositoryName: 'centreon-awie',
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

        recordIssues(
          enabledForFailure: true,
          qualityGates: [[threshold: 1, type: 'DELTA', unstable: false]],
          tool: phpCodeSniffer(id: 'phpcs', name: 'phpcs', pattern: 'codestyle-be.xml'),
          trendChartType: 'NONE',
          referenceJobName: 'centreon-awie/master'
        )

        if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
          withSonarQubeEnv('SonarQube') {
            sh "./centreon-build/jobs/awie/${serie}/mon-awie-analysis.sh"
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
        sh "./centreon-build/jobs/awie/${serie}/mon-awie-package.sh centos7"
      }
    },
    'centos8': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/awie/${serie}/mon-awie-package.sh centos8"
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Package stage failure.');
    }
  }

  stage('Bundle') {
    parallel 'centos7': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/awie/${serie}/mon-awie-bundle.sh centos7"
      }
    },
    'centos8': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/awie/${serie}/mon-awie-bundle.sh centos8"
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Bundle stage failure.');
    }
  }

  stage('Acceptance tests') {
    parallel 'centos7': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/awie/${serie}/mon-awie-acceptance.sh centos7"
        junit 'xunit-reports/**/*.xml'
        if (currentBuild.result == 'UNSTABLE')
          currentBuild.result = 'FAILURE'
        archiveArtifacts allowEmptyArchive: true, artifacts: 'acceptance-logs/*.txt, acceptance-logs/*.png'
      }
    },
    'centos8': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/awie/${serie}/mon-awie-acceptance.sh centos8"
        junit 'xunit-reports/**/*.xml'
        if (currentBuild.result == 'UNSTABLE')
          currentBuild.result = 'FAILURE'
        archiveArtifacts allowEmptyArchive: true, artifacts: 'acceptance-logs/*.txt, acceptance-logs/*.png'
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Acceptance tests stage failure.');
    }
  }

  if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
    stage('Delivery') {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/awie/${serie}/mon-awie-delivery.sh"
      }
      if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
        error('Delivery stage failure.');
      }
    }
  }
} catch(e) {
  if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
    slackSend channel: "#monitoring-metrology", color: "#F30031", message: "*FAILURE*: `CENTREON AWIE` <${env.BUILD_URL}|build #${env.BUILD_NUMBER}> on branch ${env.BRANCH_NAME}\n*COMMIT*: <https://github.com/centreon/centreon-awie/commit/${source.COMMIT}|here> by ${source.COMMITTER}\n*INFO*: ${e}"
  }
  currentBuild.result = 'FAILURE'
}
