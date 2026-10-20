@block @block_pdfexport @mod_exelearning
Feature: Download eXeLearning activities as PDF
  In order to read eXeLearning content offline
  As a student
  I need to download the static content of published eXeLearning activities

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity    | course | name         | packagefilepath                                                   |
      | exelearning | C1     | eXe pages    | ../../blocks/pdfexport/tests/fixtures/multipage-gradable.elpx     |

  Scenario: A student downloads an eXeLearning activity and the course PDF
    Given the PDF Export block of "C1" publishes:
      | eXe pages |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "eXe pages" in the "PDF Export" "block"
    And following "Download PDF of eXe pages" should download between "1000" and "2000000" bytes
    And following "Download course PDF" should download between "2000" and "2000000" bytes
