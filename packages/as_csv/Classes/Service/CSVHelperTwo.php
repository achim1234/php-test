<?php

namespace Packages\AsCsv\Service;

class CSVHelperTwo
{

    public function exportToFile()
    {

        // Example array of results from the database, each result has 10 values
        #
        $results = [
            ['ID22', 'Name', 'Email', 'Age', 'Country', 'City', 'Phone', 'Status', 'Date', 'Comment', 'Test'],
            [1, 'John Doe', 'john@example.com', 28, 'USA', 'New York', '1234567890', 'Active', '2024-12-16', 'No comments'],
            [2, 'Jane Smith', 'jane@example.com', 34, 'Canada', 'Toronto', '0987654321', 'Inactive', '2024-12-15', 'Pending review'],
            // Add more rows as needed
        ];

// Set the headers for the CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="export.csv"');

// Open the output stream
        $output = fopen('php://output', 'w');

// Write the data to the CSV
        foreach ($results as $row) {
            fputcsv($output, $row);
        }

// Close the output stream
        fclose($output);

        // End script execution
        exit;

    }

}