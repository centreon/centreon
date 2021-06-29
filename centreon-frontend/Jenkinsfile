/*
** Variables.
*/
import groovy.json.JsonSlurper

properties([buildDiscarder(logRotator(numToKeepStr: '50'))])
def serie = '21.10'
def maintenanceBranch = "${serie}.x"
if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
} else if ((env.BRANCH_NAME == 'master') || (env.BRANCH_NAME == maintenanceBranch)) {
  env.BUILD = 'REFERENCE'
} else {
  env.BUILD = 'CI'
}

def buildBranch = env.BRANCH_NAME
if (env.CHANGE_BRANCH) {
  buildBranch = env.CHANGE_BRANCH
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

stage('Sonar analysis') {
  node {
      dir('centreon-frontend') {
        checkout scm
      }
      checkoutCentreonBuild(buildBranch)
      discoverGitReferenceBuild()
      withSonarQubeEnv('SonarQubeDev') {  
          sh "./centreon-build/jobs/frontend/${serie}/frontend-analysis.sh"
      }
    
      def reportFilePath = "target/sonar/report-task.txt"
      def reportTaskFileExists = fileExists "${reportFilePath}"
      if (reportTaskFileExists) {
        echo "Found report task file"
        def taskProps = readProperties file: "${reportFilePath}"
        echo "taskId[${taskProps['ceTaskId']}]"
        timeout(time: 10, unit: 'MINUTES') {
          while (true) {
              sleep 5
              def taskStatusResult    =
                  sh(returnStdout: true,
                     script: "curl -s -X GET -u ${authString} \'${sonarProps['sonar.host.url']}/api/ce/task?id=${taskProps['ceTaskId']}\'")
                  echo "taskStatusResult[${taskStatusResult}]"
              def taskStatus  = new JsonSlurper().parseText(taskStatusResult).task.status
              echo "taskStatus[${taskStatus}]"
              // Status can be SUCCESS, ERROR, PENDING, or IN_PROGRESS. The last two indicate it's
              // not done yet.
              if (taskStatus != "IN_PROGRESS" && taskStatus != "PENDING") {
                  break;
              }
              def qualityGate = waitForQualityGate()
              if (qualityGate.status != 'OK') {
                currentBuild.result = 'FAIL'
              }
          }
        }
    }

      source = readProperties file: 'source.properties'
      env.VERSION = "${source.VERSION}"
      env.RELEASE = "${source.RELEASE}"
      sh "./centreon-build/jobs/frontend/${serie}/frontend-sources.sh"
      stash includes: '**', name: 'centreonui-centreon-build'
      stash includes: '**', name: 'uicontext-centreon-build'
  }
  if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Sonar analysis stage failure');
    }
}

stage('Unit tests') {
  parallel 'centreon-ui': {
    node {
      unstash name: 'centreonui-centreon-build'
      sh "./centreon-build/jobs/frontend/${serie}/centreon-ui/centreonui-unittest.sh"
      junit 'ut.xml'
      discoverGitReferenceBuild()
        recordIssues(
          enabledForFailure: true,
          qualityGates: [[threshold: 1, type: 'NEW', unstable: false]],
          failOnError: true,
          tool: esLint(id: 'centreon-ui', pattern: 'codestyle.xml'),
          trendChartType: 'NONE'
        )
      stash includes: '**', name: 'centreon-frontend-centreonui-centreon-build'
      archiveArtifacts allowEmptyArchive: true, artifacts: 'snapshots/*.png'
    }
  },
  'ui-context': {
    node {
      unstash name: 'uicontext-centreon-build'
      sh "./centreon-build/jobs/frontend/${serie}/ui-context/uicontext-unittest.sh"
      discoverGitReferenceBuild()
        recordIssues(
          enabledForFailure: true,
          qualityGates: [[threshold: 1, type: 'NEW', unstable: false]],
          failOnError: true,
          tool: esLint(id: 'ui-context', pattern: 'codestyle.xml'),
          trendChartType: 'NONE'
        )
    }
  }
}


if (env.BUILD == 'REFERENCE') {
  stage ('Delivery') {
    node {
      unstash name: 'centreonui-centreon-build'
      sh "./centreon-build/jobs/frontend/${serie}/centreon-ui/centreonui-delivery.sh"
    }}
}
