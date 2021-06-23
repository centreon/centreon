/*
** Variables.
*/

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

stage('Source') {
  parallel 'centreon-ui': {
    node {
      dir('centreon-frontend') {
        checkout scm
      }
      checkoutCentreonBuild(buildBranch)
      sh "./centreon-build/jobs/frontend/centreon-ui/${serie}/centreonui-source.sh"
      source = readProperties file: 'source.properties'
      env.VERSION = "${source.VERSION}"
      env.RELEASE = "${source.RELEASE}"
      stash includes: '**', name: 'centreon-frontend-centreonui-centreon-build'
    }
  },
  'ui-context': {
    node {
      dir('centreon-frontend') {
        checkout scm
      }
      checkoutCentreonBuild(buildBranch)
      sh "./centreon-build/jobs/frontend/ui-context/${serie}/uicontext-source.sh"
      source = readProperties file: 'source.properties'
      env.VERSION = "${source.VERSION}"
      env.RELEASE = "${source.RELEASE}"
      stash includes: '**', name: 'centreon-frontend-uicontext-centreon-build'
    }
  }
}

stage('Unit tests') {
  parallel 'centreon-ui': {
    node {
      unstash name: 'centreon-frontend-centreonui-centreon-build'
      sh "./centreon-build/jobs/frontend/centreon-ui/${serie}/centreonui-unittest.sh"
      junit 'ut.xml'
      discoverGitReferenceBuild()
        recordIssues(
          enabledForFailure: true,
          qualityGates: [[threshold: 1, type: 'NEW', unstable: false]],
          failOnError: true,
          tool: esLint(id: 'centreon-ui', pattern: 'codestyle.xml'),
          trendChartType: 'NONE'
        )

      archiveArtifacts allowEmptyArchive: true, artifacts: 'snapshots/*.png'
    }
  },
  'ui-context': {
    node {
      unstash name: 'centreon-frontend-uicontext-centreon-build'
      sh "./centreon-build/jobs/frontend/ui-context/${serie}/uicontext-unittest.sh"
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
      unstash name: 'centreon-frontend-uicontext-centreon-build'
      sh "./centreon-build/jobs/frontend/centreon-ui/${serie}/centreonui-delivery.sh"
    }}
}
