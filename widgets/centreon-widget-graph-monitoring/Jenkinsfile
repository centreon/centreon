/*
** Variables.
*/
def serie = '22.04'
def stableBranch = "master"
def devBranch = "develop"
env.REF_BRANCH = stableBranch

if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
} else if (env.BRANCH_NAME == stableBranch) {
  env.BUILD = 'REFERENCE'
} else if (env.BRANCH_NAME == devBranch) {
  env.BUILD = 'QA'
} else {
  env.BUILD = 'CI'
}

def buildBranch = env.BRANCH_NAME
if (env.CHANGE_BRANCH) {
  buildBranch = env.CHANGE_BRANCH
}

/*
** Functions
*/
def isStableBuild() {
  return ((env.BUILD == 'REFERENCE') || (env.BUILD == 'QA'))
}

def checkoutCentreonBuild(buildBranch) {
  def getCentreonBuildGitConfiguration = { branchName -> [
    $class: 'GitSCM',
    branches: [[name: "refs/heads/${branchName}"]],
    doGenerateSubmoduleConfigurations: false,
    userRemoteConfigs: [[
      $class: 'UserRemoteConfig',
      url: "ssh://git@github.com/centreon/centreon-build.git"
    ]]
  ]}

  dir('centreon-build') {
    try {
      checkout(getCentreonBuildGitConfiguration(buildBranch))
    } catch(e) {
      echo "branch '${buildBranch}' does not exist in centreon-build, then fallback to master"
      checkout(getCentreonBuildGitConfiguration('master'))
    }
  }
}

/*
** Pipeline code.
*/
stage('Deliver sources') {
  node {
    checkoutCentreonBuild(buildBranch)
    env.WIDGET = 'graph-monitoring'
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
  stage('Unit tests // RPMs packaging // Sonar analysis') {
    parallel 'unit tests backend centos7': {
      node {
        checkoutCentreonBuild(buildBranch)
        sh "./centreon-build/jobs/widgets/${serie}/widget-unittest.sh centos7"
        if (currentBuild.result == 'UNSTABLE')
          currentBuild.result = 'FAILURE'

        if (env.CHANGE_ID) { // pull request to comment with coding style issues
          ViolationsToGitHub([
            repositoryName: 'centreon-widget-graph-monitoring',
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
        withSonarQubeEnv('SonarQubeDev') {
          sh "./centreon-build/jobs/widgets/${serie}/widget-analysis.sh"
        }
        timeout(time: 10, unit: 'MINUTES') {
          def qualityGate = waitForQualityGate()
          if (qualityGate.status != 'OK') {
            currentBuild.result = 'FAIL'
          }
        }
      }
    },
    'RPM packaging centos7': {
      node {
        checkoutCentreonBuild(buildBranch)
        sh "./centreon-build/jobs/widgets/${serie}/widget-package.sh centos7"
        archiveArtifacts artifacts: 'rpms-centos7.tar.gz'
        stash name: "rpms-centos7", includes: 'output/noarch/*.rpm'
        sh 'rm -rf output'
      }
    },
    'RPM packaging alma8': {
      node {
        checkoutCentreonBuild(buildBranch)
        sh "./centreon-build/jobs/widgets/${serie}/widget-package.sh alma8"
        archiveArtifacts artifacts: 'rpms-alma8.tar.gz'
        stash name: "rpms-alma8", includes: 'output/noarch/*.rpm'
        sh 'rm -rf output'      
      }
    }
    
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Unit tests stage failure.');
    }
  }

  if ((env.BUILD == 'RELEASE') || (env.BUILD == 'QA') || (env.BUILD == 'CI')) {
    stage('Delivery') {
      node {
        checkoutCentreonBuild(buildBranch)
        unstash 'rpms-alma8'
        unstash 'rpms-centos7'
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
        message: "*FAILURE*: `CENTREON WIDGET GRAPH MONITORING` <${env.BUILD_URL}|build #${env.BUILD_NUMBER}> on branch ${env.BRANCH_NAME}\n" +
            "*COMMIT*: <https://github.com/centreon/centreon-widget-graph-monitoring/commit/${source.COMMIT}|here> by ${source.COMMITTER}\n" +
            "*INFO*: ${e}"
  }

  currentBuild.result = 'FAILURE'
}
