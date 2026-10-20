@block @block_pdfexport
Feature: Publish course content and download it as PDF
  In order to study offline
  As a teacher
  I need to publish course resources that students can download as PDF

  Background:
    Given the following "courses" exist:
      | fullname | shortname | numsections |
      | Course 1 | C1        | 2           |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | teacher2 | Teacher   | Two      |
      | student1 | Student   | One      |
      | outsider | Out       | Sider    |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | section | name         | content              | visible |
      | page     | C1     | 1       | Welcome page | Welcome to the course | 1       |
      | book     | C1     | 2       | Intro book   |                      | 1       |
      | page     | C1     | 2       | Hidden page  | Secret notes         | 0       |
    And the following "mod_book > chapters" exist:
      | book       | title       | content            |
      | Intro book | Chapter one | First chapter text |

  @javascript
  Scenario: A teacher adds the block and publishes resources from several sections
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I add the "PDF Export" block
    Then I should see "No resources have been published for this block." in the "PDF Export" "block"
    And I click on "Manage downloadable resources" "link" in the "PDF Export" "block"
    And I set the field "Welcome page" to "1"
    And I set the field "Intro book" to "1"
    And I press "Save changes"
    And I should see "Welcome page" in the "PDF Export" "block"
    And I should see "Intro book" in the "PDF Export" "block"
    And I should see "Download course PDF" in the "PDF Export" "block"
    And I click on "Manage downloadable resources" "link" in the "PDF Export" "block"
    And the field "Welcome page" matches value "1"
    And the field "Hidden page" matches value "0"

  @javascript
  Scenario: A teacher sets a default authorship and license and a resource-specific one
    Given the PDF Export block of "C1" publishes:
      | Welcome page |
      | Intro book   |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Manage downloadable resources" "link" in the "PDF Export" "block"
    And I should not see "Custom license name"
    When I set the following fields to these values:
      | Authors or organizations | Ana García     |
      | Content license          | Custom license |
    And I set the following fields to these values:
      | Custom license name    | Campus terms              |
      | License URL (optional) | https://example.org/terms |
      | Specific authorship and license for Intro book | 1 |
    And I set the following fields to these values:
      | Intro book: authors or organizations (empty: default authors) | Book Team |
      | Intro book: license                                           | CC BY-SA 4.0 |
    And I press "Save changes"
    And I click on "Manage downloadable resources" "link" in the "PDF Export" "block"
    Then the following fields match these values:
      | Authors or organizations                                       | Ana García                |
      | Content license                                                | Custom license            |
      | Custom license name                                            | Campus terms              |
      | License URL (optional)                                         | https://example.org/terms |
      | Specific authorship and license for Intro book                 | 1                         |
      | Intro book: authors or organizations (empty: default authors)  | Book Team                 |
      | Intro book: license                                            | CC BY-SA 4.0              |
      | Specific authorship and license for Welcome page               | 0                         |
    And I should not see "Welcome page: license"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And following "Download course PDF" should download between "2000" and "2000000" bytes
    And following "Download PDF of Intro book" should download between "1000" and "2000000" bytes

  @javascript
  Scenario: A custom license needs a name and an HTTP(S) URL
    Given the PDF Export block of "C1" publishes:
      | Welcome page |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Manage downloadable resources" "link" in the "PDF Export" "block"
    When I set the following fields to these values:
      | Content license | Custom license |
    And I set the field "License URL (optional)" to "javascript:alert(1)"
    And I press "Save changes"
    Then I should see "Enter a name for the custom license."
    And I should see "Enter a valid HTTP or HTTPS URL."

  @javascript
  Scenario: A teacher styles the course PDF cover and clears the style
    Given the PDF Export block of "C1" publishes:
      | Welcome page |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Manage downloadable resources" "link" in the "PDF Export" "block"
    And I expand all fieldsets
    When I set the field "Cover CSS" to "@import url(https://example.org/x.css);"
    And I press "Save changes"
    Then I should see "The cover CSS contains characters that are not allowed."
    And I expand all fieldsets
    And I set the field "Cover CSS" to ".pdfexport-cover-title { position: absolute }"
    And I press "Save changes"
    And I should see "The property \"position\" is not supported in the cover CSS."
    And I expand all fieldsets
    And I set the field "Cover CSS" to ".pdfexport-cover-title { color: #123456; font-size: 30pt; }"
    And I press "Save changes"
    And I click on "Manage downloadable resources" "link" in the "PDF Export" "block"
    And I expand all fieldsets
    And the field "Cover CSS" matches value ".pdfexport-cover-title { color: #123456; font-size: 30pt; }"
    And the field "Welcome page" matches value "1"
    And following "Download the course PDF with the saved selection" should download between "2000" and "2000000" bytes
    And I set the field "Cover CSS" to ""
    And I press "Save changes"
    And I click on "Manage downloadable resources" "link" in the "PDF Export" "block"
    And I expand all fieldsets
    And the field "Cover CSS" matches value ""
    And the field "Welcome page" matches value "1"

  Scenario: A student downloads individual and course PDFs of published resources only
    Given the PDF Export block of "C1" publishes:
      | Welcome page |
      | Intro book   |
      | Hidden page  |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Welcome page" in the "PDF Export" "block"
    And I should see "Intro book" in the "PDF Export" "block"
    And I should not see "Hidden page" in the "PDF Export" "block"
    And I should not see "Manage downloadable resources"
    And following "Download course PDF" should download between "2000" and "2000000" bytes
    And following "Download PDF of Welcome page" should download between "1000" and "2000000" bytes
    And following "Download PDF of Intro book" should download between "1000" and "2000000" bytes

  Scenario: Hidden or unpublished resources cannot be downloaded by changing the URL
    Given the PDF Export block of "C1" publishes:
      | Welcome page |
      | Hidden page  |
    When I log in as "student1"
    Then the PDF Export download page of "Hidden page" in "C1" should be refused with "This resource is not published for download or is not available to you."
    And the PDF Export download page of "Intro book" in "C1" should be refused with "This resource is not published for download or is not available to you."

  Scenario: Users outside the course cannot download its content
    Given the PDF Export block of "C1" publishes:
      | Welcome page |
    When I log in as "outsider"
    Then the PDF Export download page of "the course" in "C1" should be refused with "You cannot enrol yourself in this course"

  Scenario Outline: Students and non-editing teachers cannot manage the published resources or the cover style
    Given the PDF Export block of "C1" publishes:
      | Welcome page |
    When I log in as "<user>"
    Then the PDF Export management page of "the course" in "C1" should be refused with "Sorry, but you do not currently have permissions to do that"

    Examples:
      | user     |
      | student1 |
      | teacher2 |

  Scenario: An empty selection is explained instead of producing an empty PDF
    Given the PDF Export block of "C1" publishes:
      | Hidden page |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "No resources have been published for this block." in the "PDF Export" "block"
    And the PDF Export download page of "the course" in "C1" should be refused with "There is no published content available to you for export."
